<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Support\BranchContext;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::withCurrentInventory()->with('category', 'unit')
            ->search($request->query('q'))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->query('category')))
            ->when($request->query('stock') === 'low', fn ($q) => $q->lowStock())
            ->when($request->query('stock') === 'out', fn ($q) => $q->outOfStock())
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::orderBy('name')->get();

        return view('products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        return view('products.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['image'] = $this->handleImage($request);
        $data['sku'] = ($data['sku'] ?? null) ?: $this->generateSku();
        $stock = (float) $data['stock_quantity'];
        $alert = (float) ($data['alert_quantity'] ?? 5);
        unset($data['stock_quantity'], $data['alert_quantity']);

        $product = DB::transaction(function () use ($data, $stock, $alert) {
            $product = Product::create($data);
            $now = now();
            $currentBranchId = BranchContext::id();
            $rows = Branch::query()->pluck('id')->map(fn ($branchId) => [
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'stock_quantity' => (int) $branchId === $currentBranchId ? $stock : 0,
                'alert_quantity' => (int) $branchId === $currentBranchId ? $alert : 5,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if ($rows) {
                BranchInventory::insert($rows);
            }

            return $product;
        });
        ActivityLog::record('created', "Created product {$product->name}", $product);

        return redirect()->route('products.index')->with('success', __('Product created successfully.'));
    }

    public function edit(Product $product): View
    {
        return view('products.edit', array_merge($this->formData(), compact('product')));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateData($request, $product);
        if ($image = $this->handleImage($request)) {
            $data['image'] = $image;
        }
        $stock = (float) $data['stock_quantity'];
        $alert = (float) ($data['alert_quantity'] ?? 5);
        unset($data['stock_quantity'], $data['alert_quantity']);

        DB::transaction(function () use ($data, $product, $stock, $alert): void {
            $product->update($data);
            BranchInventory::updateOrCreate(
                ['branch_id' => BranchContext::id(), 'product_id' => $product->id],
                ['stock_quantity' => $stock, 'alert_quantity' => $alert]
            );
        });
        ActivityLog::record('updated', "Updated product {$product->name}", $product);

        return redirect()->route('products.index')->with('success', __('Product updated successfully.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->delete();
        ActivityLog::record('deleted', "Deleted product {$name}");

        return redirect()->route('products.index')->with('success', __('Product deleted.'));
    }

    protected function validateData(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'.($product ? ','.$product->id : '')],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:products,barcode'.($product ? ','.$product->id : '')],
            'category_id' => ['nullable', 'exists:categories,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stock_quantity' => ['required', 'numeric', 'min:0'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    protected function handleImage(Request $request): ?string
    {
        if ($request->hasFile('image')) {
            $request->validate(['image' => ['image', 'max:2048']]);

            return $request->file('image')->store('products', 'public');
        }

        return null;
    }

    protected function generateSku(): string
    {
        do {
            $sku = 'SKU-'.strtoupper(Str::random(6));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

    protected function formData(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ];
    }
}
