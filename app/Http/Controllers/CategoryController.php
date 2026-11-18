<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::withCount('products')->orderBy('name')->paginate(15);

        return view('categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
        ]);
        Category::create($data);

        return back()->with('success', __('Category added.'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,'.$category->id],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $category->update($data);

        return back()->with('success', __('Category updated.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', __('Category deleted.'));
    }
}
