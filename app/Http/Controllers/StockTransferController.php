<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in($this->statuses())],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();
        $visibleQuery = StockTransfer::query()
            ->when(! $user->hasRole('Super Admin'), function ($query) use ($user): void {
                $query->where(function ($query) use ($user): void {
                    $query->where('source_branch_id', $user->branch_id)
                        ->orWhere('destination_branch_id', $user->branch_id);
                });
            });
        $stats = [
            'pending' => (clone $visibleQuery)->where('status', StockTransfer::STATUS_PENDING_APPROVAL)->count(),
            'in_transit' => (clone $visibleQuery)->whereIn('status', [
                StockTransfer::STATUS_IN_TRANSIT,
                StockTransfer::STATUS_PARTIALLY_RECEIVED,
            ])->count(),
            'completed' => (clone $visibleQuery)->whereIn('status', [
                StockTransfer::STATUS_RECEIVED,
                StockTransfer::STATUS_RECEIVED_WITH_DISCREPANCY,
            ])->count(),
        ];
        $query = (clone $visibleQuery)
            ->with(['sourceBranch', 'destinationBranch', 'creator'])
            ->withCount('items')
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['q'] ?? null, fn ($query, $term) => $query->where('reference', 'like', "%{$term}%"))
            ->latest('id');

        $transfers = $query->paginate(20)->withQueryString();

        return view('transfers.index', [
            'transfers' => $transfers,
            'statuses' => $this->statuses(),
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): View
    {
        $sourceBranch = Branch::query()->whereKey(BranchContext::id())
            ->where('is_active', true)->firstOrFail();
        $branches = Branch::query()->where('is_active', true)
            ->whereKeyNot($sourceBranch->id)->orderBy('branch_number')->get();
        $products = Product::query()->active()
            ->whereHas('currentInventory', fn ($query) => $query->where('stock_quantity', '>', 0))
            ->withCurrentInventory()->with('unit')->orderBy('name')->get();

        return view('transfers.create', compact('sourceBranch', 'branches', 'products'));
    }

    public function store(Request $request, StockTransferService $service): RedirectResponse
    {
        $sourceBranch = Branch::query()->whereKey(BranchContext::id())
            ->where('is_active', true)->firstOrFail();
        $data = $request->validate([
            'destination_branch_id' => [
                'required', 'integer',
                Rule::exists('branches', 'id')->where('is_active', true),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:product_id,quantity'],
            'items.*.product_id' => [
                'required', 'integer', 'distinct:strict',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999.999'],
        ]);

        if ((int) $data['destination_branch_id'] === (int) $sourceBranch->id) {
            return back()->withInput()->withErrors([
                'destination_branch_id' => __('The destination must be different from the source branch.'),
            ]);
        }

        $transfer = $service->create(
            $request->user(),
            (int) $sourceBranch->id,
            (int) $data['destination_branch_id'],
            $data['items'],
            $data['notes'] ?? null
        );
        ActivityLog::record('created', "Created stock transfer {$transfer->reference}", $transfer);

        return redirect()->route('transfers.show', $transfer)
            ->with('success', __('Transfer request submitted for Super Admin approval.'));
    }

    public function show(Request $request, StockTransfer $transfer): View
    {
        $this->authorizeTransferVisibility($request, $transfer);
        $transfer = $this->loadTransferDetails($transfer);
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $sourceUser = (int) $user->branch_id === (int) $transfer->source_branch_id;
        $destinationUser = (int) $user->branch_id === (int) $transfer->destination_branch_id;

        return view('transfers.show', [
            'transfer' => $transfer,
            'canApprove' => $isSuperAdmin && $transfer->status === StockTransfer::STATUS_PENDING_APPROVAL,
            'canReject' => $isSuperAdmin && $transfer->status === StockTransfer::STATUS_PENDING_APPROVAL,
            'canDispatch' => $transfer->status === StockTransfer::STATUS_APPROVED && ($isSuperAdmin || $sourceUser),
            'canReceive' => in_array($transfer->status, [
                StockTransfer::STATUS_IN_TRANSIT,
                StockTransfer::STATUS_PARTIALLY_RECEIVED,
            ], true) && ($isSuperAdmin || $destinationUser),
            'canCancel' => in_array($transfer->status, [
                StockTransfer::STATUS_PENDING_APPROVAL,
                StockTransfer::STATUS_APPROVED,
            ], true) && ($isSuperAdmin || $sourceUser),
        ]);
    }

    public function approve(Request $request, StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        $transfer = $service->approve($transfer, $request->user());
        ActivityLog::record('updated', "Approved stock transfer {$transfer->reference}", $transfer);

        return back()->with('success', __('Transfer approved. The source branch can now dispatch it.'));
    }

    public function reject(Request $request, StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);
        $transfer = $service->reject($transfer, $request->user(), $data['rejection_reason']);
        ActivityLog::record('updated', "Rejected stock transfer {$transfer->reference}", $transfer);

        return back()->with('success', __('Transfer rejected.'));
    }

    public function dispatch(Request $request, StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        $this->authorizeBranchSide($request, $transfer, 'source_branch_id');
        $transfer = $service->dispatch($transfer, $request->user());
        ActivityLog::record('updated', "Dispatched stock transfer {$transfer->reference}", $transfer);

        return back()->with('success', __('Transfer dispatched. Source stock has been deducted.'));
    }

    public function receiveForm(Request $request, StockTransfer $transfer): View
    {
        $this->authorizeBranchSide($request, $transfer, 'destination_branch_id');
        abort_unless(in_array($transfer->status, [
            StockTransfer::STATUS_IN_TRANSIT,
            StockTransfer::STATUS_PARTIALLY_RECEIVED,
        ], true), 404);

        $transfer->load(['sourceBranch', 'destinationBranch', 'items.product.unit', 'items.receiptItems']);

        return view('transfers.receive', compact('transfer'));
    }

    public function receive(Request $request, StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        $this->authorizeBranchSide($request, $transfer, 'destination_branch_id');
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array:transfer_item_id,quantity_received,discrepancy_reason'],
            'items.*.transfer_item_id' => [
                'required', 'integer', 'distinct:strict',
                Rule::exists('stock_transfer_items', 'id')->where('stock_transfer_id', $transfer->id),
            ],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999.999'],
            'items.*.discrepancy_reason' => ['nullable', 'string', 'max:1000'],
            'close_transfer' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $transfer = $service->receive(
            $transfer,
            $request->user(),
            $data['items'],
            $request->boolean('close_transfer'),
            $data['notes'] ?? null
        );
        ActivityLog::record('updated', "Recorded receipt for stock transfer {$transfer->reference}", $transfer);

        return redirect()->route('transfers.show', $transfer)
            ->with('success', __('Transfer receipt recorded.'));
    }

    public function cancel(Request $request, StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        $this->authorizeBranchSide($request, $transfer, 'source_branch_id');
        $transfer = $service->cancel($transfer, $request->user());
        ActivityLog::record('updated', "Cancelled stock transfer {$transfer->reference}", $transfer);

        return back()->with('success', __('Transfer cancelled.'));
    }

    private function authorizeTransferVisibility(Request $request, StockTransfer $transfer): void
    {
        $user = $request->user();
        if ($user->hasRole('Super Admin')) {
            return;
        }

        abort_unless(in_array((int) $user->branch_id, [
            (int) $transfer->source_branch_id,
            (int) $transfer->destination_branch_id,
        ], true), 404);
    }

    private function authorizeBranchSide(Request $request, StockTransfer $transfer, string $branchColumn): void
    {
        $user = $request->user();
        if (! $user->hasRole('Super Admin')) {
            abort_unless((int) $user->branch_id === (int) $transfer->{$branchColumn}, 404);
        }
    }

    private function loadTransferDetails(StockTransfer $transfer): StockTransfer
    {
        return $transfer->load([
            'sourceBranch',
            'destinationBranch',
            'creator',
            'approver',
            'rejector',
            'dispatcher',
            'canceller',
            'completer',
            'items.product.unit',
            'items.receiptItems',
            'receipts.receiver',
            'receipts.items.transferItem.product.unit',
        ]);
    }

    private function statuses(): array
    {
        return [
            StockTransfer::STATUS_PENDING_APPROVAL,
            StockTransfer::STATUS_APPROVED,
            StockTransfer::STATUS_IN_TRANSIT,
            StockTransfer::STATUS_PARTIALLY_RECEIVED,
            StockTransfer::STATUS_RECEIVED,
            StockTransfer::STATUS_RECEIVED_WITH_DISCREPANCY,
            StockTransfer::STATUS_REJECTED,
            StockTransfer::STATUS_CANCELLED,
        ];
    }
}
