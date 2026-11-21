<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\BranchReportFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request, BranchReportFilters $branchFilters): View
    {
        $filters = $branchFilters->validate($request);
        [$from, $to] = $branchFilters->range($filters);
        $query = Expense::with('category', 'user', 'branch')
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString());
        $branchFilters->apply($query, $request, 'expenses');

        $totals = (clone $query)->selectRaw(
            'COALESCE(SUM(amount), 0) as amount, '.
            "COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as paid, ".
            "COALESCE(SUM(CASE WHEN status = 'unpaid' THEN amount ELSE 0 END), 0) as unpaid"
        )->first();

        $expenses = $query->latest('expense_date')->latest('id')->paginate(20)->withQueryString();
        $categories = ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get();
        $allCategories = ExpenseCategory::query()->withCount('expenses')->orderBy('name')->get();
        $branches = $branchFilters->branches($request);

        return view('expenses.index', compact(
            'expenses', 'categories', 'allCategories', 'branches', 'from', 'to', 'totals'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = Auth::id();
        $data['paid_at'] = $data['status'] === 'paid' ? $data['expense_date'] : null;

        $expense = Expense::create($data);
        ActivityLog::record('created', "Recorded expense: {$expense->description}", $expense);

        return redirect()->route('expenses.index', $request->only(['from', 'to', 'branch_id']))
            ->with('success', __('Expense recorded.'));
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['user_id']);
        $data['paid_at'] = $data['status'] === 'paid' ? $data['expense_date'] : null;

        $expense->update($data);
        ActivityLog::record('updated', "Updated expense: {$expense->description}", $expense);

        return back()->with('success', __('Expense updated.'));
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $description = $expense->description;
        $expense->delete();
        ActivityLog::record('deleted', "Deleted expense: {$description}");

        return back()->with('success', __('Expense deleted.'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:expense_categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        ExpenseCategory::create($data);

        return back()->with('success', __('Expense category added.'));
    }

    public function destroyCategory(ExpenseCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', __('Expense category deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => ['nullable', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:191'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,mobile,bank,other'],
            'status' => ['required', 'in:paid,unpaid'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
