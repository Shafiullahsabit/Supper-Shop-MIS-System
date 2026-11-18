<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    protected function range(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : Carbon::today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : Carbon::today()->endOfDay();

        return [$from, $to];
    }

    public function sales(Request $request): View
    {
        [$from, $to] = $this->range($request);

        $sales = Sale::whereBetween('sales.created_at', [$from, $to])->where('sales.status', '!=', 'refunded');

        $summary = [
            'orders' => (clone $sales)->count(),
            'revenue' => (clone $sales)->sum('total'),
            'discount' => (clone $sales)->sum('discount'),
            'tax' => (clone $sales)->sum('tax'),
        ];
        $summary['avg'] = $summary['orders'] ? $summary['revenue'] / $summary['orders'] : 0;

        $byDay = (clone $sales)
            ->selectRaw('DATE(sales.created_at) as d, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('d')->orderBy('d')->get();

        $byPayment = (clone $sales)
            ->selectRaw('payment_method, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('payment_method')->get();

        $byCashier = (clone $sales)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw('users.name as cashier, COUNT(*) as orders, SUM(sales.total) as revenue')
            ->groupBy('users.name')->orderByDesc('revenue')->get();

        return view('reports.sales', compact('from', 'to', 'summary', 'byDay', 'byPayment', 'byCashier'));
    }

    public function profit(Request $request): View
    {
        [$from, $to] = $this->range($request);

        $items = SaleItem::whereHas('sale', fn ($q) => $q->whereBetween('created_at', [$from, $to])->where('status', '!=', 'refunded'));

        $revenue = (clone $items)->sum(DB::raw('price * quantity - discount'));
        $cost = (clone $items)->sum(DB::raw('cost * quantity'));
        $profit = $revenue - $cost;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        $byProduct = SaleItem::select('name',
            DB::raw('SUM(quantity) as qty'),
            DB::raw('SUM(price * quantity - discount) as revenue'),
            DB::raw('SUM(cost * quantity) as cost'),
            DB::raw('SUM((price - cost) * quantity - discount) as profit'))
            ->whereHas('sale', fn ($q) => $q->whereBetween('created_at', [$from, $to])->where('status', '!=', 'refunded'))
            ->groupBy('name')->orderByDesc('profit')->get();

        return view('reports.profit', compact('from', 'to', 'revenue', 'cost', 'profit', 'margin', 'byProduct'));
    }

    public function inventory(Request $request): View
    {
        $products = Product::with('category', 'unit')->orderBy('name')->get();

        $summary = [
            'items' => $products->count(),
            'stock_units' => $products->sum('stock_quantity'),
            'cost_value' => $products->sum(fn ($p) => $p->cost_price * $p->stock_quantity),
            'retail_value' => $products->sum(fn ($p) => $p->sale_price * $p->stock_quantity),
            'low' => $products->where('stock_status', 'low')->count(),
            'out' => $products->where('stock_status', 'out')->count(),
        ];

        return view('reports.inventory', compact('products', 'summary'));
    }

    public function topProducts(Request $request): View
    {
        [$from, $to] = $this->range($request);

        $top = SaleItem::select('name',
            DB::raw('SUM(quantity) as qty'),
            DB::raw('SUM(subtotal) as revenue'))
            ->whereHas('sale', fn ($q) => $q->whereBetween('created_at', [$from, $to])->where('status', '!=', 'refunded'))
            ->groupBy('name')->orderByDesc('qty')->take(25)->get();

        return view('reports.top-products', compact('from', 'to', 'top'));
    }
}
