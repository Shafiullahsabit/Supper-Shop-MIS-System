<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $purchases = Purchase::with('supplier', 'user', 'branch')
            ->when($request->filled('q'), fn ($q) => $q->where('reference', 'like', '%'.$request->q.'%'))
            ->latest()->paginate(15)->withQueryString();

        return view('purchases.index', compact('purchases'));
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
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.cost' => ['required', 'numeric', 'min:0'],
        ]);

        $purchase = DB::transaction(function () use ($data) {
            $subtotal = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['cost']);
            $total = $subtotal - ($data['discount'] ?? 0) + ($data['tax'] ?? 0) + ($data['shipping'] ?? 0);

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
                'paid' => $data['paid'] ?? 0,
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
            ]);

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
        $purchase->load('items.product', 'supplier', 'user', 'branch');

        return view('purchases.show', compact('purchase'));
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
