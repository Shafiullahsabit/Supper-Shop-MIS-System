@extends('layouts.app')
@section('title', 'Stock Report')
@section('crumb', 'Inventory valuation')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Stock Report') }}</h2><p>{{ __('Current inventory valuation') }}</p></div>
    <div class="actions">
        <form method="GET" action="{{ route('reports.inventory') }}" class="flex gap-1">
            @if(auth()->user()->hasRole('Super Admin'))
            <select name="branch_id" class="select" style="width:auto">
                <option value="all" @selected($selectedBranch === 'all')>{{ __('All Branches') }}</option>
                @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) $selectedBranch === (string) $branch->id)>{{ $branch->branch_number }} · {{ $branch->name }}</option>@endforeach
            </select>
            @endif
            <button class="btn btn-primary">{{ __('Apply') }}</button>
        </form>
        <a class="btn btn-light" href="{{ route('reports.inventory.export', array_merge(request()->query(), ['format' => 'csv'])) }}">{{ __('CSV') }}</a>
        <a class="btn btn-light" href="{{ route('reports.inventory.export', array_merge(request()->query(), ['format' => 'xls'])) }}">{{ __('Excel') }}</a>
        <button class="btn btn-light" onclick="window.print()">@include('partials.icon',['name'=>'printer']) {{ __('Print') }}</button>
    </div>
</div>

<div class="grid grid-4 mb-2">
    <div class="card card-pad stat"><div class="ico ico-blue">@include('partials.icon',['name'=>'boxes'])</div><div class="label">{{ __('Products') }}</div><div class="value">{{ number_format($summary['items']) }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-amber">@include('partials.icon',['name'=>'money'])</div><div class="label">{{ __('Cost Value') }}</div><div class="value">{{ money($summary['cost_value']) }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-green">@include('partials.icon',['name'=>'tag'])</div><div class="label">{{ __('Retail Value') }}</div><div class="value">{{ money($summary['retail_value']) }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-red">@include('partials.icon',['name'=>'alert'])</div><div class="label">{{ __('Low / Out') }}</div><div class="value">{{ $summary['low'] }} / {{ $summary['out'] }}</div></div>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Full Stock List') }}</h3></div>
    <div class="table-wrap"><table class="data">
        <thead><tr>@if(auth()->user()->hasRole('Super Admin'))<th>{{ __('Branch') }}</th>@endif<th>{{ __('Product') }}</th><th>{{ __('Category') }}</th><th class="t-right">{{ __('Stock') }}</th><th class="t-right">{{ __('Low-stock threshold') }}</th><th class="t-right">{{ __('Cost') }}</th><th class="t-right">{{ __('Retail') }}</th><th class="t-right">{{ __('Stock Value') }}</th></tr></thead>
        <tbody>
        @forelse($inventories as $inventory)
            @php($p = $inventory->product)
            <tr>
                @if(auth()->user()->hasRole('Super Admin'))<td>{{ $inventory->branch?->branch_number }} · {{ $inventory->branch?->name }}</td>@endif
                <td class="t-title">{{ $p->name }}<div class="t-sub t-mono">{{ $p->sku }}</div></td>
                <td>{{ $p->category->name ?? '—' }}</td>
                <td class="t-right t-mono {{ $inventory->stock_quantity <= 0 ? 'text-red' : '' }}">{{ rtrim(rtrim(number_format($inventory->stock_quantity,3),'0'),'.') }} {{ $p->unit->short_name ?? '' }}</td>
                <td class="t-right t-mono">{{ rtrim(rtrim(number_format($inventory->alert_quantity,3),'0'),'.') }}</td>
                <td class="t-right t-mono">{{ money($p->cost_price) }}</td>
                <td class="t-right t-mono">{{ money($p->sale_price) }}</td>
                <td class="t-right t-mono fw-700">{{ money($p->cost_price * $inventory->stock_quantity) }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ auth()->user()->hasRole('Super Admin') ? 8 : 7 }}"><div class="empty">@include('partials.icon',['name'=>'boxes'])<h4>{{ __('No inventory found') }}</h4></div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
