<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        $todaySales = Sale::whereDate('created_at', $today)->where('status', '!=', 'refunded');
        $todayRevenue = (clone $todaySales)->sum('total');
        $todayOrders = (clone $todaySales)->count();

        $monthRevenue = Sale::where('created_at', '>=', $monthStart)
            ->where('status', '!=', 'refunded')->sum('total');

        // Gross profit this month
        $monthProfit = SaleItem::whereHas('sale', function ($q) use ($monthStart) {
            $q->where('created_at', '>=', $monthStart)->where('status', '!=', 'refunded');
        })->sum(DB::raw('(price - cost) * quantity - discount'));

        $productCount = Product::where('is_active', true)->count();
        $lowStock = Product::active()->lowStock()->with('unit')->orderBy('stock_quantity')->take(8)->get();
        $lowStockCount = Product::active()->lowStock()->count();

        // Last 7 days revenue trend
        $trend = Sale::where('created_at', '>=', Carbon::today()->subDays(6))
            ->where('status', '!=', 'refunded')
            ->selectRaw('DATE(created_at) as d, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('d')->pluck('revenue', 'd');

        $chart = collect();
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $chart->push([
                'label' => $day->translatedFormat('D'),
                'date' => $day->translatedFormat('M j'),
                'value' => round((float) ($trend[$day->format('Y-m-d')] ?? 0), 2),
            ]);
        }

        $recentSales = Sale::with('cashier', 'customer')->latest()->take(8)->get();

        $topProducts = SaleItem::select('name', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(subtotal) as revenue'))
            ->whereHas('sale', fn ($q) => $q->where('created_at', '>=', $monthStart))
            ->groupBy('name')->orderByDesc('qty')->take(5)->get();

        return view('dashboard', compact(
            'todayRevenue', 'todayOrders', 'monthRevenue', 'monthProfit',
            'productCount', 'lowStock', 'lowStockCount', 'chart', 'recentSales', 'topProducts'
        ));
    }
}
