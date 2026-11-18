<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Support\BranchContext;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::withCurrentInventory()->with('category', 'unit')
            ->search($request->query('q'))
            ->when($request->query('filter') === 'low', fn ($q) => $q->lowStock())
            ->when($request->query('filter') === 'out', fn ($q) => $q->outOfStock())
            ->orderByStock()
            ->paginate(20)->withQueryString();

        $stats = [
            'total' => Product::count(),
            'low' => Product::active()->lowStock()->count(),
            'out' => Product::active()->outOfStock()->count(),
            'stock_value' => DB::table('branch_inventories')
                ->join('products', 'products.id', '=', 'branch_inventories.product_id')
                ->where('branch_inventories.branch_id', BranchContext::id())
                ->sum(DB::raw('products.cost_price * branch_inventories.stock_quantity')),
        ];

        return view('inventory.index', compact('products', 'stats'));
    }

    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:add,subtract'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $product) {
            $inventory = BranchInventory::where('branch_id', BranchContext::id())
                ->where('product_id', $product->id)
                ->lockForUpdate()->firstOrFail();
            $before = (float) $inventory->stock_quantity;
            $delta = $data['type'] === 'add' ? $data['quantity'] : -$data['quantity'];
            $after = max(0, $before + $delta);

            $inventory->update(['stock_quantity' => $after]);

            StockAdjustment::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
            ]);
        });

        ActivityLog::record('adjusted', "Stock adjusted for {$product->name}", $product);

        return back()->with('success', __('Stock updated for :name.', ['name' => $product->name]));
    }

    public function history(Request $request): View
    {
        $adjustments = StockAdjustment::with('product', 'user', 'branch')
            ->latest()->paginate(25);

        return view('inventory.history', compact('adjustments'));
    }
}
