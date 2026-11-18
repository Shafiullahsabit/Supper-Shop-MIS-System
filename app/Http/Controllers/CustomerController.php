<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::withCount('sales')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('phone', 'like', '%'.$request->q.'%')
                ->orWhere('email', 'like', '%'.$request->q.'%'))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function show(Customer $customer): View
    {
        $customer->load(['sales' => fn ($q) => $q->latest()->take(30)]);

        return view('customers.show', compact('customer'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $customer = Customer::create($data);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'customer' => $customer->only('id', 'name', 'phone')]);
        }

        return back()->with('success', __('Customer added.'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request));

        return back()->with('success', __('Customer updated.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return back()->with('success', __('Customer deleted.'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'loyalty_points' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
