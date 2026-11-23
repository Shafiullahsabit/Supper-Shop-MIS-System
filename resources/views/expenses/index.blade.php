@extends('layouts.app')
@section('title', 'Expenses')
@section('crumb', 'Daily expenses and supplier balances')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Expenses') }}</h2><p>{{ $from->translatedFormat('d M Y') }} — {{ $to->translatedFormat('d M Y') }}</p></div>
    <div class="actions">
        @can('expenses.manage')
            <button class="btn btn-light" onclick="SS.openModal('expenseCategories')">{{ __('Expense Categories') }}</button>
            <button class="btn btn-primary" onclick="SS.openModal('addExpense')">@include('partials.icon',['name'=>'plus']) {{ __('Add Expense') }}</button>
        @endcan
    </div>
</div>

<form method="GET" class="toolbar" action="{{ route('expenses.index') }}">
    <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input" style="width:auto">
    <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input" style="width:auto">
    @if(auth()->user()->hasRole('Super Admin'))
    <select name="branch_id" class="select" style="width:auto">
        <option value="all" @selected(request('branch_id', 'all') === 'all')>{{ __('All Branches') }}</option>
        @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->branch_number }} · {{ $branch->name }}</option>@endforeach
    </select>
    @endif
    <button class="btn btn-light">{{ __('Apply') }}</button>
    <a class="btn btn-light" href="{{ route('expenses.export', array_merge(request()->query(), ['format' => 'csv'])) }}">{{ __('CSV') }}</a>
    <a class="btn btn-light" href="{{ route('expenses.export', array_merge(request()->query(), ['format' => 'xls'])) }}">{{ __('Excel') }}</a>
</form>

<div class="grid grid-3 mb-2">
    @foreach([
        ['label' => __('Total Expenses'), 'key' => 'amount', 'icon' => 'money', 'color' => 'ico-blue'],
        ['label' => __('Paid Expenses'), 'key' => 'paid', 'icon' => 'check-circle', 'color' => 'ico-green'],
        ['label' => __('Unpaid Expenses'), 'key' => 'unpaid', 'icon' => 'alert', 'color' => 'ico-amber'],
    ] as $summary)
        <div class="card card-pad stat">
            <div class="ico {{ $summary['color'] }}">@include('partials.icon',['name' => $summary['icon']])</div>
            <div class="label">{{ $summary['label'] }}</div>
            <div class="expense-currency-summary">
                @foreach(['AFN', 'USD'] as $currency)
                    <div><span>{{ $currency }}</span><strong>{{ money($totals[$currency][$summary['key']], $currency) }}</strong></div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<p class="expense-currency-note">{{ __('Totals and charts keep currencies separate; no exchange conversion is applied.') }}</p>
<div class="expense-trend-grid">
    @foreach(['AFN', 'USD'] as $currency)
        @php
            $series = collect($expenseTrend[$currency]);
            $maxExpense = max(1, (float) $series->max('value'));
            $currencyLabel = $currency === 'AFN' ? __('Afghan Afghani (AFN)') : __('US Dollar (USD)');
        @endphp
        <section class="card expense-trend-card" data-expense-currency="{{ $currency }}">
            <div class="card-head expense-trend-head">
                <div><h3>{{ __('Expense trend') }} · {{ $currencyLabel }}</h3><div class="sub">{{ $chartInterval === 'month' ? __('Monthly expenses') : __('Weekly expenses') }}</div></div>
                <strong class="t-mono">{{ money($totals[$currency]['amount'], $currency) }}</strong>
            </div>
            <div class="expense-trend-scroll">
                <div class="expense-trend-bars" role="img" aria-label="{{ $chartInterval === 'month' ? __('Monthly expenses') : __('Weekly expenses') }} — {{ $currency }}">
                    @foreach($series as $point)
                        <div class="expense-trend-column" title="{{ $point['date'] }}: {{ money($point['value'], $currency) }}">
                            <span class="expense-trend-value">{{ $point['value'] > 0 ? number_format($point['value'], 0) : '—' }}</span>
                            <div class="expense-trend-track">
                                <div class="expense-trend-bar" style="height: {{ $point['value'] > 0 ? max(4, ($point['value'] / $maxExpense) * 100) : 2 }}%"></div>
                            </div>
                            <span class="expense-trend-label">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Date') }}</th>@if(auth()->user()->hasRole('Super Admin'))<th>{{ __('Branch') }}</th>@endif<th>{{ __('Category') }}</th><th>{{ __('Description') }}</th><th>{{ __('Payment Method') }}</th><th>{{ __('Status') }}</th><th>{{ __('Currency') }}</th><th class="t-right">{{ __('Amount') }}</th>@can('expenses.manage')<th></th>@endcan</tr></thead>
            <tbody>
            @forelse($expenses as $expense)
                <tr>
                    <td>{{ $expense->expense_date->translatedFormat('d M Y') }}</td>
                    @if(auth()->user()->hasRole('Super Admin'))<td>{{ $expense->branch?->branch_number }} · {{ $expense->branch?->name }}</td>@endif
                    <td>{{ $expense->category?->name ?? '—' }}</td>
                    <td><div class="t-title">{{ $expense->description }}</div>@if($expense->note)<div class="t-sub">{{ $expense->note }}</div>@endif</td>
                    <td><span class="badge badge-gray">{{ __(ucfirst($expense->payment_method)) }}</span></td>
                    <td>@if($expense->status === 'paid')<span class="badge badge-green">{{ __('Paid') }}</span>@else<span class="badge badge-amber">{{ __('Unpaid') }}</span>@endif</td>
                    <td>{{ $expense->currency }}</td>
                    <td class="t-right t-mono fw-700">{{ money($expense->amount, $expense->currency) }}</td>
                    @can('expenses.manage')
                    <td>
                        <div class="flex gap-1" style="justify-content:flex-end">
                            <button class="icon-btn" onclick='SS.edit("editExpense",{{ Illuminate\Support\Js::from([
                                "expense_date" => $expense->expense_date->format("Y-m-d"),
                                "expense_category_id" => $expense->expense_category_id,
                                "description" => $expense->description,
                                "amount" => $expense->amount,
                                "currency" => $expense->currency,
                                "payment_method" => $expense->payment_method,
                                "status" => $expense->status,
                                "note" => $expense->note,
                            ]) }}); document.getElementById("editExpenseForm").action="{{ route('expenses.update',$expense) }}"'>@include('partials.icon',['name'=>'edit'])</button>
                            <form method="POST" action="{{ route('expenses.destroy',$expense) }}" onsubmit="return SS.confirmSubmit(this,'{{ __('Delete this expense?') }}')">
                                @csrf @method('DELETE')<button class="icon-btn danger">@include('partials.icon',['name'=>'trash'])</button>
                            </form>
                        </div>
                    </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="{{ (auth()->user()->hasRole('Super Admin') ? 8 : 7) + (auth()->user()->can('expenses.manage') ? 1 : 0) }}"><div class="empty">@include('partials.icon',['name'=>'money'])<h4>{{ __('No expenses recorded') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $expenses->links('partials.pagination') }}</div>
</div>

@can('expenses.manage')
@php $defaultExpenseCurrency = setting('currency_symbol', '$') === '؋' ? 'AFN' : 'USD'; @endphp
@foreach([
    'addExpense' => [__('Add Expense'), route('expenses.store'), false],
    'editExpense' => [__('Edit Expense'), '#', true],
] as $modalId => $config)
<div class="modal-overlay" id="{{ $modalId }}" style="display:none">
    <form class="modal wide" method="POST" @if($config[2]) id="editExpenseForm" @else action="{{ $config[1] }}" @endif>
        @csrf @if($config[2]) @method('PUT') @endif
        <div class="modal-head"><h3>{{ $config[0] }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('{{ $modalId }}')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="form-row">
                <div class="field"><label>{{ __('Expense Date') }}</label><input class="input" type="date" name="expense_date" value="{{ now()->format('Y-m-d') }}" required></div>
                <div class="field"><label>{{ __('Category') }}</label>
                    <select class="select" name="expense_category_id"><option value="">{{ __('— none —') }}</option>@foreach($allCategories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                </div>
            </div>
            <div class="form-row">
                <div class="field"><label>{{ __('Description') }}</label><input class="input" name="description" maxlength="191" required></div>
                <div class="field"><label>{{ __('Amount') }}</label><input class="input t-mono" type="number" step="0.01" min="0.01" name="amount" required></div>
            </div>
            <div class="form-row">
                <div class="field"><label>{{ __('Currency') }}</label>
                    <select class="select" name="currency" required>
                        <option value="AFN" @selected(old('currency', $defaultExpenseCurrency) === 'AFN')>{{ __('Afghan Afghani (AFN)') }}</option>
                        <option value="USD" @selected(old('currency', $defaultExpenseCurrency) === 'USD')>{{ __('US Dollar (USD)') }}</option>
                    </select>
                </div>
                <div class="field"><label>{{ __('Payment Method') }}</label>
                    <select class="select" name="payment_method">@foreach(['cash','card','mobile','bank','other'] as $method)<option value="{{ $method }}">{{ __(ucfirst($method)) }}</option>@endforeach</select>
                </div>
                <div class="field"><label>{{ __('Status') }}</label><select class="select" name="status"><option value="paid">{{ __('Paid') }}</option><option value="unpaid">{{ __('Unpaid') }}</option></select></div>
            </div>
            <div class="field" style="margin-bottom:0"><label>{{ __('Note') }}</label><textarea class="input" name="note"></textarea></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('{{ $modalId }}')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</div>
@endforeach

<div class="modal-overlay" id="expenseCategories" style="display:none">
    <section class="modal wide" role="dialog" aria-modal="true" aria-labelledby="expenseCategoriesTitle">
        <div class="modal-head"><h3 id="expenseCategoriesTitle">{{ __('Expense Categories') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('expenseCategories')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <form method="POST" action="{{ route('expense-categories.store') }}" class="form-row">
                @csrf
                <div class="field"><label>{{ __('Category Name') }}</label><input class="input" name="name" maxlength="100" required></div>
                <div class="field"><label>{{ __('Description') }}</label><input class="input" name="description"></div>
                <div class="field" style="align-self:end"><button class="btn btn-primary">{{ __('Add Category') }}</button></div>
            </form>
            <div class="table-wrap mt-2"><table class="data"><thead><tr><th>{{ __('Category') }}</th><th>{{ __('Expenses') }}</th><th></th></tr></thead><tbody>
                @forelse($allCategories as $category)
                    <tr><td class="t-title">{{ $category->name }}</td><td>{{ $category->expenses_count }}</td><td class="t-right">
                        <form method="POST" action="{{ route('expense-categories.destroy',$category) }}" onsubmit="return SS.confirmSubmit(this,'{{ __('Delete this category? Existing expenses will be kept uncategorized.') }}')">
                            @csrf @method('DELETE')<button class="icon-btn danger">@include('partials.icon',['name'=>'trash'])</button>
                        </form>
                    </td></tr>
                @empty
                    <tr><td colspan="3"><div class="empty" style="padding:20px">{{ __('No expense categories yet') }}</div></td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('expenseCategories')">{{ __('Close') }}</button></div>
    </section>
</div>
@endcan
@endsection
