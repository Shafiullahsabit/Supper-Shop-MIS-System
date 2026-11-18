<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        $units = Unit::withCount('products')->orderBy('name')->get();

        return view('units.index', compact('units'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'short_name' => ['required', 'string', 'max:20', 'unique:units,short_name'],
            'allow_decimal' => ['nullable', 'boolean'],
        ]);
        Unit::create($data);

        return back()->with('success', __('Unit added.'));
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'short_name' => ['required', 'string', 'max:20', 'unique:units,short_name,'.$unit->id],
            'allow_decimal' => ['nullable', 'boolean'],
        ]);
        $unit->update($data);

        return back()->with('success', __('Unit updated.'));
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->delete();

        return back()->with('success', __('Unit deleted.'));
    }
}
