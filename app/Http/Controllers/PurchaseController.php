<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\BranchReportFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(Request $request, BranchReportFilters $branchFilters): View
    {
        $filters = $branchFilters->validate($request);
        [$from, $to] = $branchFilters->range($filters);
        $query = Purchase::with('supplier', 'user', 'branch')
            ->when($request->filled('q'), fn ($q) => $q->where('reference', 'like', '%'.$request->q.'%'))
            ->whereDate('purchase_date', '>=', $from->toDateString())
            ->whereDate('purchase_date', '<=', $to->toDateString());
        $branchFilters->apply($query, $request, 'purchases');
        $purchases = $query->latest()->paginate(15)->withQueryString();
        $branches = $branchFilters->branches($request);

        return view('purchases.index', compact('purchases', 'from', 'to', 'branches'));
    }

    public function create(): View
    {
        return view('purchases.create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'products' => Product::active()->withCurrentInventory()
                ->orderBy('name')->get(['id', 'name', 'sku', 'barcode', 'cost_price']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date'],
            'status' => ['required', 'in:received,pending,ordered'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,card,mobile,bank,other'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.cost' => ['required', 'numeric', 'min:0'],
        ]);

        $purchase = DB::transaction(function () use ($data) {
            $subtotal = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['cost']);
            $total = round($subtotal - ($data['discount'] ?? 0) + ($data['tax'] ?? 0) + ($data['shipping'] ?? 0), 2);
            $paid = round((float) ($data['paid'] ?? 0), 2);

            if ($paid > $total) {
                throw ValidationException::withMessages([
                    'paid' => __('The initial payment cannot exceed the purchase total.'),
                ]);
            }

            $purchase = Purchase::create([
                'reference' => Purchase::generateReference(),
                'supplier_id' => $data['supplier_id'] ?? null,
                'user_id' => Auth::id(),
                'purchase_date' => $data['purchase_date'],
                'subtotal' => $subtotal,
                'discount' => $data['discount'] ?? 0,
                'tax' => $data['tax'] ?? 0,
                'shipping' => $data['shipping'] ?? 0,
                'total' => $total,
                'paid' => $paid,
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
            ]);

            if ($paid > 0) {
                $purchase->payments()->create([
                    'user_id' => Auth::id(),
                    'amount' => $paid,
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'paid_at' => $data['purchase_date'].' 00:00:00',
                    'note' => __('Initial payment at purchase creation.'),
                ]);
            }

            foreach ($data['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['cost'];
                $purchase->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'cost' => $item['cost'],
                    'subtotal' => $lineTotal,
                ]);

                // Received purchases add stock and update cost price
                if ($data['status'] === 'received') {
                    $product = Product::find($item['product_id']);
                    BranchInventory::where('branch_id', $purchase->branch_id)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()->firstOrFail()
                        ->increment('stock_quantity', $item['quantity']);
                    $product->update(['cost_price' => $item['cost']]);
                }
            }

            return $purchase;
        });

        ActivityLog::record('created', "Recorded purchase {$purchase->reference}", $purchase);

        return redirect()->route('purchases.show', $purchase)->with('success', __('Purchase recorded.'));
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load('items.product', 'supplier', 'user', 'branch', 'payments.user');

        return view('purchases.show', compact('purchase'));
    }

    public function recordPayment(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,mobile,bank,other'],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $purchase): void {
            $lockedPurchase = Purchase::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
            $amount = round((float) $data['amount'], 2);

            if ($amount > $lockedPurchase->outstanding) {
                throw ValidationException::withMessages([
                    'amount' => __('The payment cannot exceed the outstanding balance.'),
                ]);
            }

            $lockedPurchase->payments()->create([
                'user_id' => Auth::id(),
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'paid_at' => $data['paid_at'],
                'note' => $data['note'] ?? null,
            ]);
            $lockedPurchase->update(['paid' => round((float) $lockedPurchase->paid + $amount, 2)]);
        });

        ActivityLog::record('payment', "Recorded payment for purchase {$purchase->reference}", $purchase);

        return redirect()->route('purchases.show', $purchase)->with('success', __('Supplier payment recorded.'));
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        DB::transaction(function () use ($purchase) {
            if ($purchase->status === 'received') {
                foreach ($purchase->items as $item) {
                    if ($item->product_id) {
                        BranchInventory::where('branch_id', $purchase->branch_id)
                            ->where('product_id', $item->product_id)
                            ->lockForUpdate()->firstOrFail()
                            ->decrement('stock_quantity', $item->quantity);
                    }
                }
            }
            $purchase->delete();
        });

        ActivityLog::record('deleted', "Deleted purchase {$purchase->reference}");

        return redirect()->route('purchases.index')->with('success', __('Purchase deleted and stock reversed.'));
    }
}
