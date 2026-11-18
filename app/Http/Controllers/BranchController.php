<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $branches = Branch::withCount('users')->orderBy('branch_number')->paginate(20);

        return view('branches.index', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'branch_number' => ['required', 'string', 'max:32', 'regex:/^[0-9]+$/', 'unique:branches,branch_number'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        DB::transaction(function () use ($data): void {
            $branch = Branch::create($data + ['is_active' => true]);
            $now = now();

            Product::query()->select('id')->orderBy('id')->chunk(500, function ($products) use ($branch, $now): void {
                $rows = $products->map(fn (Product $product) => [
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'stock_quantity' => 0,
                    'alert_quantity' => 5,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows) {
                    BranchInventory::insert($rows);
                }
            });
        });

        return back()->with('success', __('Branch created. New branch stock starts at zero.'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'branch_number' => [
                'required', 'string', 'max:32', 'regex:/^[0-9]+$/',
                Rule::unique('branches', 'branch_number')->ignore($branch->id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $data['is_active'] && Branch::where('is_active', true)->count() === 1) {
            return back()->with('error', __('At least one branch must remain active.'));
        }

        if (! $data['is_active'] && $branch->users()->where('is_active', true)
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Super Admin'))
            ->exists()) {
            return back()->with('error', __('Reassign or deactivate this branch’s staff before deactivating the branch.'));
        }

        $activeBranchId = BranchContext::id();
        $branch->update($data);

        if ($activeBranchId === $branch->id && ! $branch->is_active) {
            session()->forget('active_branch_id');
        }

        return back()->with('success', __('Branch updated.'));
    }

    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
        ]);

        session(['active_branch_id' => (int) $data['branch_id']]);

        return back()->with('success', __('Active branch changed.'));
    }
}
