<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $sales = Sale::with('cashier', 'customer', 'branch')
            ->when($request->filled('q'), fn ($q) => $q->where('invoice_no', 'like', '%'.$request->q.'%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->filled('payment'), fn ($q) => $q->where('payment_method', $request->payment))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            // Cashiers only see their own sales
            ->when(! Auth::user()->can('sales.refund') && Auth::user()->hasRole('Cashier'),
                fn ($q) => $q->where('user_id', Auth::id()))
            ->latest()->paginate(20)->withQueryString();

        $totals = [
            'count' => (clone $sales)->total(),
        ];

        return view('sales.index', compact('sales', 'totals'));
    }

    public function show(Sale $sale): View
    {
        $sale->load('items.product', 'customer', 'cashier', 'branch');

        return view('sales.show', compact('sale'));
    }

    public function refund(Request $request, Sale $sale, SaleService $service): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $service->refund($sale, $request->input('reason'));

        return back()->with('success', __('Sale :invoice refunded and stock restored.', ['invoice' => $sale->invoice_no]));
    }
}
