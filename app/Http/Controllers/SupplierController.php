<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::withCount('products')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('company', 'like', '%'.$request->q.'%')
                ->orWhere('phone', 'like', '%'.$request->q.'%'))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validated($request));

        return back()->with('success', __('Supplier added.'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));

        return back()->with('success', __('Supplier updated.'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return back()->with('success', __('Supplier deleted.'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
