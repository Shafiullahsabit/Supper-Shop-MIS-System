<?php

namespace App\Http\Controllers;

use App\Models\BranchInventory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\ReportExportService;
use App\Support\BranchReportFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function sales(Request $request, BranchReportFilters $branchFilters): View
    {
        $filters = $branchFilters->validate($request);
        [$from, $to] = $branchFilters->range($filters);

        $sales = Sale::whereBetween('sales.created_at', [$from, $to])->where('sales.status', '!=', 'refunded');
        $branchFilters->apply($sales, $request, 'sales');

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

        $branches = $branchFilters->branches($request);
        $selectedBranch = $filters['branch_id'];

        return view('reports.sales', compact('from', 'to', 'summary', 'byDay', 'byPayment', 'byCashier', 'branches', 'selectedBranch'));
    }

    public function profit(Request $request, BranchReportFilters $branchFilters): View
    {
        $filters = $branchFilters->validate($request);
        [$from, $to] = $branchFilters->range($filters);

        $items = $this->saleItemsFor($request, $branchFilters, $from, $to);
        $revenue = (clone $items)->sum(DB::raw('price * quantity - discount'));
        $cost = (clone $items)->sum(DB::raw('cost * quantity'));
        $profit = $revenue - $cost;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        $byProduct = $this->saleItemsFor($request, $branchFilters, $from, $to)->select('name',
            DB::raw('SUM(quantity) as qty'),
            DB::raw('SUM(price * quantity - discount) as revenue'),
            DB::raw('SUM(cost * quantity) as cost'))
            ->selectRaw('SUM((price - cost) * quantity - discount) as profit')
            ->groupBy('name')->orderByDesc('profit')->get();
        $branches = $branchFilters->branches($request);
        $selectedBranch = $filters['branch_id'];

        return view('reports.profit', compact('from', 'to', 'revenue', 'cost', 'profit', 'margin', 'byProduct', 'branches', 'selectedBranch'));
    }

    public function inventory(Request $request, BranchReportFilters $branchFilters): View
    {
        $filters = $branchFilters->validate($request);
        $inventories = BranchInventory::query()
            ->with(['product.category', 'product.unit', 'branch'])
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->orderBy('branch_id')
            ->orderBy('product_id');
        $branchFilters->apply($inventories, $request, 'branch_inventories');
        $inventories = $inventories->get();

        $summary = [
            'items' => $inventories->count(),
            'stock_units' => $inventories->sum('stock_quantity'),
            'cost_value' => $inventories->sum(fn ($inventory) => $inventory->product->cost_price * $inventory->stock_quantity),
            'retail_value' => $inventories->sum(fn ($inventory) => $inventory->product->sale_price * $inventory->stock_quantity),
            'low' => $inventories->filter(fn ($inventory) => $inventory->stock_quantity > 0
                && $inventory->stock_quantity <= $inventory->alert_quantity)->count(),
            'out' => $inventories->where('stock_quantity', '<=', 0)->count(),
        ];
        $branches = $branchFilters->branches($request);
        $selectedBranch = $filters['branch_id'];

        return view('reports.inventory', compact('inventories', 'summary', 'branches', 'selectedBranch'));
    }

    public function topProducts(Request $request, BranchReportFilters $branchFilters): View
    {
        $filters = $branchFilters->validate($request);
        [$from, $to] = $branchFilters->range($filters);

        $top = $this->saleItemsFor($request, $branchFilters, $from, $to)->select('name',
            DB::raw('SUM(quantity) as qty'),
            DB::raw('SUM(subtotal) as revenue'))
            ->groupBy('name')->orderByDesc('qty')->take(25)->get();
        $branches = $branchFilters->branches($request);
        $selectedBranch = $filters['branch_id'];

        return view('reports.top-products', compact('from', 'to', 'top', 'branches', 'selectedBranch'));
    }

    public function exportSales(
        Request $request,
        BranchReportFilters $branchFilters,
        ReportExportService $exports,
        string $format
    ): StreamedResponse {
        $this->ensureFormat($format);
        $filters = $branchFilters->validate($request);
        [$from, $to] = $branchFilters->range($filters);
        $query = DB::table('sales')
            ->leftJoin('branches', 'branches.id', '=', 'sales.branch_id')
            ->leftJoin('users', 'users.id', '=', 'sales.user_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereBetween('sales.created_at', [$from, $to])
            ->where('sales.status', '!=', 'refunded');
        $branchFilters->apply($query, $request, 'sales');
        $query->select([
            'sales.invoice_no', 'sales.created_at', 'branches.name as branch_name',
            'users.name as cashier_name', 'customers.name as customer_name',
            'sales.payment_method', 'sales.subtotal', 'sales.discount',
            'sales.tax', 'sales.total', 'sales.status',
        ])->orderBy('sales.created_at')->orderBy('sales.id');
        $rows = (function () use ($query) {
            foreach ($query->cursor() as $sale) {
                yield [
                    $sale->invoice_no, $sale->created_at, $sale->branch_name,
                    $sale->cashier_name, $sale->customer_name ?? __('Walk-in'),
                    $sale->payment_method, (float) $sale->subtotal,
                    (float) $sale->discount, (float) $sale->tax,
                    (float) $sale->total, $sale->status,
                ];
            }
        })();

        return $exports->download('sales-report', [
            __('Invoice'), __('Date'), __('Branch'), __('Cashier'), __('Customer'),
            __('Payment Method'), __('Subtotal'), __('Discount'), __('Tax'), __('Total'), __('Status'),
        ], $rows, $format);
    }

    public function exportPurchases(
        Request $request,
        BranchReportFilters $branchFilters,
        ReportExportService $exports,
        string $format
    ): StreamedResponse {
        $this->ensureFormat($format);
        $filters = $branchFilters->validate($request);
        [$from, $to] = $branchFilters->range($filters);
        $query = DB::table('purchases')
            ->leftJoin('branches', 'branches.id', '=', 'purchases.branch_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->leftJoin('users', 'users.id', '=', 'purchases.user_id')
            ->whereDate('purchases.purchase_date', '>=', $from->toDateString())
            ->whereDate('purchases.purchase_date', '<=', $to->toDateString());
        $branchFilters->apply($query, $request, 'purchases');
        $query->select([
            'purchases.reference', 'purchases.purchase_date', 'branches.name as branch_name',
            'suppliers.name as supplier_name', 'users.name as recorded_by',
            'purchases.subtotal', 'purchases.discount', 'purchases.tax',
            'purchases.shipping', 'purchases.total', 'purchases.paid', 'purchases.status',
        ])->orderBy('purchases.purchase_date')->orderBy('purchases.id');
        $rows = (function () use ($query) {
            foreach ($query->cursor() as $purchase) {
                yield [
                    $purchase->reference, $purchase->purchase_date, $purchase->branch_name,
                    $purchase->supplier_name, $purchase->recorded_by,
                    (float) $purchase->subtotal, (float) $purchase->discount,
                    (float) $purchase->tax, (float) $purchase->shipping,
                    (float) $purchase->total, (float) $purchase->paid,
                    max(0, (float) $purchase->total - (float) $purchase->paid),
                    $purchase->status,
                ];
            }
        })();

        return $exports->download('purchases-report', [
            __('Reference'), __('Date'), __('Branch'), __('Supplier'), __('Recorded by'),
            __('Subtotal'), __('Discount'), __('Tax'), __('Shipping'), __('Total'),
            __('Paid'), __('Balance due'), __('Status'),
        ], $rows, $format);
    }

    public function exportExpenses(
        Request $request,
        BranchReportFilters $branchFilters,
        ReportExportService $exports,
        string $format
    ): StreamedResponse {
        $this->ensureFormat($format);
        $filters = $branchFilters->validate($request);
        [$from, $to] = $branchFilters->range($filters);
        $query = DB::table('expenses')
            ->leftJoin('branches', 'branches.id', '=', 'expenses.branch_id')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
            ->whereDate('expenses.expense_date', '>=', $from->toDateString())
            ->whereDate('expenses.expense_date', '<=', $to->toDateString());
        $branchFilters->apply($query, $request, 'expenses');
        $query->select([
            'expenses.expense_date', 'branches.name as branch_name',
            'expense_categories.name as category_name', 'expenses.description',
            'expenses.amount', 'expenses.currency', 'expenses.payment_method', 'expenses.status',
            'expenses.paid_at', 'users.name as recorded_by', 'expenses.note',
        ])->orderBy('expenses.expense_date')->orderBy('expenses.id');
        $rows = (function () use ($query) {
            foreach ($query->cursor() as $expense) {
                yield [
                    $expense->expense_date, $expense->branch_name, $expense->category_name,
                    $expense->description, (float) $expense->amount, $expense->currency,
                    $expense->payment_method, $expense->status, $expense->paid_at,
                    $expense->recorded_by, $expense->note,
                ];
            }
        })();

        return $exports->download('expenses-report', [
            __('Date'), __('Branch'), __('Category'), __('Description'), __('Amount'),
            __('Currency'), __('Payment Method'), __('Status'), __('Paid At'), __('Recorded by'), __('Note'),
        ], $rows, $format);
    }

    public function exportInventory(
        Request $request,
        BranchReportFilters $branchFilters,
        ReportExportService $exports,
        string $format
    ): StreamedResponse {
        $this->ensureFormat($format);
        $branchFilters->validate($request);
        $query = DB::table('branch_inventories')
            ->join('products', 'products.id', '=', 'branch_inventories.product_id')
            ->leftJoin('branches', 'branches.id', '=', 'branch_inventories.branch_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->where('products.is_active', true);
        $branchFilters->apply($query, $request, 'branch_inventories');
        $query->select([
            'branches.name as branch_name', 'products.name', 'products.sku',
            'categories.name as category_name', 'branch_inventories.stock_quantity',
            'branch_inventories.alert_quantity', 'units.short_name as unit_name',
            'products.cost_price', 'products.sale_price',
        ])->orderBy('branches.branch_number')->orderBy('products.name');
        $rows = (function () use ($query) {
            foreach ($query->cursor() as $inventory) {
                yield [
                    $inventory->branch_name, $inventory->name, $inventory->sku,
                    $inventory->category_name, (float) $inventory->stock_quantity,
                    (float) $inventory->alert_quantity, $inventory->unit_name,
                    (float) $inventory->cost_price, (float) $inventory->sale_price,
                    (float) $inventory->cost_price * (float) $inventory->stock_quantity,
                ];
            }
        })();

        return $exports->download('inventory-current', [
            __('Branch'), __('Product'), __('SKU'), __('Category'), __('Stock'),
            __('Low-stock threshold'), __('Unit'), __('Cost'), __('Retail'), __('Stock Value'),
        ], $rows, $format);
    }

    public function exportProfit(
        Request $request,
        BranchReportFilters $branchFilters,
        ReportExportService $exports,
        string $format
    ): StreamedResponse {
        $this->ensureFormat($format);
        $filters = $branchFilters->validate($request);
        [$from, $to] = $branchFilters->range($filters);
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.created_at', [$from, $to])
            ->where('sales.status', '!=', 'refunded');
        $branchFilters->apply($query, $request, 'sales');
        $query->select('sale_items.name')
            ->selectRaw('SUM(sale_items.quantity) as quantity')
            ->selectRaw('SUM(sale_items.price * sale_items.quantity - sale_items.discount) as revenue')
            ->selectRaw('SUM(sale_items.cost * sale_items.quantity) as cost')
            ->selectRaw('SUM((sale_items.price - sale_items.cost) * sale_items.quantity - sale_items.discount) as profit')
            ->groupBy('sale_items.name')
            ->orderByDesc('profit');
        $rows = (function () use ($query) {
            foreach ($query->cursor() as $product) {
                yield [
                    $product->name, (float) $product->quantity, (float) $product->revenue,
                    (float) $product->cost, (float) $product->profit,
                    $product->revenue > 0 ? round($product->profit / $product->revenue * 100, 2) : 0,
                ];
            }
        })();

        return $exports->download('profit-report', [
            __('Product'), __('Qty Sold'), __('Revenue'), __('Cost'), __('Profit'), __('Margin (%)'),
        ], $rows, $format);
    }

    private function saleItemsFor(
        Request $request,
        BranchReportFilters $branchFilters,
        Carbon $from,
        Carbon $to
    ): Builder {
        return SaleItem::query()->whereHas('sale', function ($query) use ($request, $branchFilters, $from, $to): void {
            $query->whereBetween('sales.created_at', [$from, $to])
                ->where('sales.status', '!=', 'refunded');
            $branchFilters->apply($query, $request, 'sales');
        });
    }

    private function ensureFormat(string $format): void
    {
        if (! in_array($format, ['csv', 'xls'], true)) {
            abort(404);
        }
    }
}
