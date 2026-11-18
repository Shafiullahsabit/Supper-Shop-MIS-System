<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\HeldSale;
use App\Models\Product;
use App\Models\Sale;
use App\Services\DrawerService;
use App\Services\SaleService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        $session = CashSession::where('user_id', Auth::id())
            ->where('branch_id', BranchContext::id())
            ->where('status', 'open')->latest('opened_at')->first();

        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $products = Product::active()->withCurrentInventory()->with('unit', 'category')->orderBy('name')->get();
        $customers = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']);
        $held = HeldSale::where('user_id', Auth::id())->latest()->get();

        return view('pos.index', compact('session', 'categories', 'products', 'customers', 'held'));
    }

    /**
     * Barcode / keyword lookup used by the scanner + search box.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        // Exact barcode/sku match first (scanner path)
        $product = Product::active()
            ->where('barcode', $term)
            ->orWhere('sku', $term)
            ->first();

        if ($product) {
            return response()->json(['exact' => true, 'products' => [$this->transform($product)]]);
        }

        $products = Product::active()->withCurrentInventory()->search($term)->limit(20)->get()
            ->map(fn ($p) => $this->transform($p));

        return response()->json(['exact' => false, 'products' => $products]);
    }

    public function checkout(Request $request, SaleService $service): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'payment_method' => ['required', 'in:cash,card,mobile'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
            'open_drawer' => ['nullable', 'boolean'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $sale = $service->checkout($data, Auth::id());

        $drawer = null;
        if ($request->boolean('open_drawer') && $data['payment_method'] === 'cash') {
            $drawer = app(DrawerService::class)->kick();
        }

        return response()->json([
            'ok' => true,
            'sale_id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'receipt_url' => route('pos.receipt', $sale),
            'drawer' => $drawer,
        ]);
    }

    /**
     * Fresh product catalogue for the offline cache (IndexedDB) to hydrate from.
     */
    public function catalog(): JsonResponse
    {
        $products = Product::active()->withCurrentInventory()->with('unit', 'category')->orderBy('name')->get()
            ->map(fn ($p) => $this->transform($p));

        return response()->json([
            'products' => $products,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Flush a batch of sales that were completed while the terminal was offline.
     * Each sale carries a client_uuid so re-sends are idempotent (never double-posted).
     * Oversell is tolerated — the goods already left the shop — and flagged for reconcile.
     */
    public function sync(Request $request, SaleService $service): JsonResponse
    {
        $data = $request->validate([
            'sales' => ['required', 'array', 'min:1', 'max:200'],
            'sales.*.client_uuid' => ['required', 'string', 'max:64'],
            'sales.*.items' => ['required', 'array', 'min:1'],
            'sales.*.items.*.product_id' => ['required', 'integer'],
            'sales.*.items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'sales.*.items.*.price' => ['nullable', 'numeric', 'min:0'],
            'sales.*.items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'sales.*.customer_id' => ['nullable', 'integer'],
            'sales.*.payment_method' => ['required', 'in:cash,card,mobile'],
            'sales.*.paid' => ['nullable', 'numeric', 'min:0'],
            'sales.*.discount' => ['nullable', 'numeric', 'min:0'],
            'sales.*.offline_created_at' => ['nullable', 'date'],
            'sales.*.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $results = [];

        foreach ($data['sales'] as $row) {
            try {
                // A customer deleted since the offline sale falls back to walk-in.
                if (! empty($row['customer_id']) && ! Customer::whereKey($row['customer_id'])->exists()) {
                    $row['customer_id'] = null;
                }
                $row['is_offline'] = true;

                $options = ['allow_oversell' => true];
                if (Auth::user()->hasRole('Super Admin') && ! empty($row['branch_id'])) {
                    $options['branch_id'] = (int) $row['branch_id'];
                }

                $sale = $service->checkout($row, Auth::id(), $options);

                $results[] = [
                    'client_uuid' => $row['client_uuid'],
                    'status' => $sale->wasRecentlyCreated ? 'synced' : 'duplicate',
                    'invoice_no' => $sale->invoice_no,
                    'sale_id' => $sale->id,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'client_uuid' => $row['client_uuid'],
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json(['ok' => true, 'results' => $results]);
    }

    public function receipt(Sale $sale): View
    {
        $sale->load('items', 'customer', 'cashier', 'branch');

        return view('pos.receipt', compact('sale'));
    }

    public function drawer(DrawerService $service): JsonResponse
    {
        return response()->json($service->kick());
    }

    // ---- Hold / resume ----
    public function hold(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cart' => ['required', 'array', 'min:1'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'total' => ['required', 'numeric'],
        ]);

        $held = HeldSale::create([
            'reference' => 'HOLD-'.strtoupper(substr(md5(uniqid()), 0, 6)),
            'user_id' => Auth::id(),
            'customer_id' => $data['customer_id'] ?? null,
            'cart' => $data['cart'],
            'total' => $data['total'],
        ]);

        return response()->json(['ok' => true, 'id' => $held->id, 'reference' => $held->reference]);
    }

    public function resume(HeldSale $held): JsonResponse
    {
        abort_unless($held->user_id === Auth::id(), 403);

        $payload = [
            'id' => $held->id,
            'reference' => $held->reference,
            'customer_id' => $held->customer_id,
            'cart' => $held->cart,
        ];
        $held->delete();

        return response()->json(['ok' => true, 'held' => $payload]);
    }

    protected function transform(Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'barcode' => $p->barcode,
            'price' => (float) $p->sale_price,
            'tax_rate' => (float) $p->tax_rate,
            'stock' => (float) $p->stock_quantity,
            'unit' => $p->unit?->short_name ?? 'pcs',
            'allow_decimal' => (bool) ($p->unit?->allow_decimal ?? false),
            'category_id' => $p->category_id,
            'category' => $p->category?->name,
            'color' => $p->category?->color ?? '#2563eb',
        ];
    }
}
