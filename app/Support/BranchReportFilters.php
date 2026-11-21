<?php

namespace App\Support;

use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BranchReportFilters
{
    public function validate(Request $request): array
    {
        $rules = [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];

        if ($request->user()->hasRole('Super Admin')) {
            $branchIds = Branch::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
            $rules['branch_id'] = ['nullable', Rule::in(array_merge(['all'], $branchIds))];
        }

        $filters = $request->validate($rules);

        if (! empty($filters['from']) && ! empty($filters['to'])
            && Carbon::parse($filters['to'])->lt(Carbon::parse($filters['from']))) {
            throw ValidationException::withMessages([
                'to' => __('The end date must be on or after the start date.'),
            ]);
        }

        $filters['branch_id'] = $request->user()->hasRole('Super Admin')
            ? ($filters['branch_id'] ?? 'all')
            : (string) ($request->user()->branch_id ?? 0);

        return $filters;
    }

    public function range(array $filters): array
    {
        $from = isset($filters['from'])
            ? Carbon::parse($filters['from'])->startOfDay()
            : Carbon::today()->startOfMonth();
        $to = isset($filters['to'])
            ? Carbon::parse($filters['to'])->endOfDay()
            : Carbon::today()->endOfDay();

        return [$from, $to];
    }

    public function apply(EloquentBuilder|QueryBuilder $query, Request $request, string $table): EloquentBuilder|QueryBuilder
    {
        if ($request->user()->hasRole('Super Admin')) {
            $branchId = $request->input('branch_id', 'all');

            if ($branchId !== 'all' && $branchId !== null && $branchId !== '') {
                $query->where($table.'.branch_id', $branchId);
            }

            return $query;
        }

        return $query->where($table.'.branch_id', $request->user()->branch_id ?? 0);
    }

    public function branches(Request $request): Collection
    {
        if ($request->user()->hasRole('Super Admin')) {
            return Branch::query()->orderBy('branch_number')->get();
        }

        return Branch::query()->whereKey($request->user()->branch_id)->get();
    }
}
