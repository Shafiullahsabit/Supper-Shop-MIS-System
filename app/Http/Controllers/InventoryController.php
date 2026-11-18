<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::with('category', 'unit')
            ->search($request->query('q'))
            ->when($request->query('filter') === 'low', fn ($q) => $q->lowStock())
            ->when($request->query('filter') === 'out', fn ($q) => $q->where('stock_quantity', '<=', 0))
            ->orderBy('stock_quantity')
            ->paginate(20)->withQueryString();

        $stats = [
            'total' => Product::count(),
            'low' => Product::active()->lowStock()->count(),
            'out' => Product::active()->where('stock_quantity', '<=', 0)->count(),
            'stock_value' => Product::sum(DB::raw('cost_price * stock_quantity')),
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
            $before = (float) $product->stock_quantity;
            $delta = $data['type'] === 'add' ? $data['quantity'] : -$data['quantity'];
            $after = max(0, $before + $delta);

            $product->update(['stock_quantity' => $after]);

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
        $adjustments = StockAdjustment::with('product', 'user')
            ->latest()->paginate(25);

        return view('inventory.history', compact('adjustments'));
    }
}
