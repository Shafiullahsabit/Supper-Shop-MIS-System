@extends('layouts.app')
@section('title', 'Inventory')
@section('crumb', 'Stock levels and adjustments')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Inventory') }}</h2><p>{{ __('Track and adjust stock levels') }}</p></div>
    <div class="actions"><a href="{{ route('inventory.history') }}" class="btn btn-light">@include('partials.icon',['name'=>'clock']) {{ __('Adjustment History') }}</a></div>
</div>

<div class="grid grid-4 mb-2">
    <div class="card card-pad stat"><div class="ico ico-blue">@include('partials.icon',['name'=>'boxes'])</div><div class="label">{{ __('Total Products') }}</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-amber">@include('partials.icon',['name'=>'alert'])</div><div class="label">{{ __('Low Stock') }}</div><div class="value">{{ $stats['low'] }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-red">@include('partials.icon',['name'=>'x'])</div><div class="label">{{ __('Out of Stock') }}</div><div class="value">{{ $stats['out'] }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-green">@include('partials.icon',['name'=>'money'])</div><div class="label">{{ __('Stock Value (cost)') }}</div><div class="value">{{ money($stats['stock_value']) }}</div></div>
</div>

<form method="GET" class="toolbar">
    <div class="search-box">@include('partials.icon',['name'=>'search'])<input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search products…') }}"></div>
    <select name="filter" class="select" style="width:auto" onchange="this.form.submit()">
        <option value="">{{ __('All items') }}</option>
        <option value="low" @selected(request('filter')=='low')>{{ __('Low stock') }}</option>
        <option value="out" @selected(request('filter')=='out')>{{ __('Out of stock') }}</option>
    </select>
    <button class="btn btn-light">{{ __('Filter') }}</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Category') }}</th><th class="t-right">{{ __('In Stock') }}</th><th class="t-right">{{ __('Alert At') }}</th><th>{{ __('Status') }}</th>@can('inventory.adjust')<th></th>@endcan</tr></thead>
            <tbody>
            @forelse($products as $p)
                <tr>
                    <td><div class="t-title">{{ $p->name }}</div><div class="t-sub t-mono">{{ $p->sku }}</div></td>
                    <td>@if($p->category)<span class="badge badge-blue">{{ $p->category->name }}</span>@else<span class="text-muted">—</span>@endif</td>
                    <td class="t-right t-mono fw-700">{{ rtrim(rtrim(number_format($p->stock_quantity,3),'0'),'.') }} <span class="text-muted fs-sm">{{ $p->unit->short_name ?? '' }}</span></td>
                    <td class="t-right t-mono">{{ rtrim(rtrim(number_format($p->alert_quantity,3),'0'),'.') }}</td>
                    <td>
                        @if($p->stock_status=='out')<span class="badge badge-red"><span class="dot"></span> {{ __('Out') }}</span>
                        @elseif($p->stock_status=='low')<span class="badge badge-amber"><span class="dot"></span> {{ __('Low') }}</span>
                        @else<span class="badge badge-green"><span class="dot"></span> {{ __('OK') }}</span>@endif
                    </td>
                    @can('inventory.adjust')
                    <td class="t-right">
                        <button class="btn btn-soft btn-sm" onclick='openAdjust({{ $p->id }},{{ Illuminate\Support\Js::from($p->name) }},{{ (float)$p->stock_quantity }})'>{{ __('Adjust') }}</button>
                    </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty">@include('partials.icon',['name'=>'boxes'])<h4>{{ __('No products found') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $products->links('partials.pagination') }}</div>
</div>

@can('inventory.adjust')
<div class="modal-overlay" id="adjustModal" style="display:none">
    <form class="modal" method="POST" id="adjustForm">
        @csrf
        <div class="modal-head"><h3>{{ __('Stock Adjustment') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('adjustModal')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="alert alert-info" style="margin-bottom:16px">@include('partials.icon',['name'=>'box']) <span id="adjName"></span> · {{ __('current stock:') }} <b id="adjStock"></b></div>
            <div class="form-row">
                <div class="field"><label>{{ __('Action') }}</label>
                    <select class="select" name="type"><option value="add">{{ __('Add stock (+)') }}</option><option value="subtract">{{ __('Remove stock (−)') }}</option></select></div>
                <div class="field"><label>{{ __('Quantity') }}</label><input class="input t-mono" type="number" step="0.001" min="0.001" name="quantity" required></div>
            </div>
            <div class="field"><label>{{ __('Reason') }}</label>
                <select class="select" name="reason">
                    <option value="correction">{{ __('Stock correction') }}</option><option value="damaged">{{ __('Damaged') }}</option>
                    <option value="expired">{{ __('Expired') }}</option><option value="lost">{{ __('Lost / theft') }}</option>
                    <option value="found">{{ __('Found extra') }}</option><option value="return">{{ __('Customer return') }}</option>
                </select></div>
            <div class="field" style="margin:0"><label>{{ __('Note (optional)') }}</label><textarea class="input" name="note"></textarea></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('adjustModal')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Apply Adjustment') }}</button></div>
    </form>
</div>
@push('scripts')
<script>
    function openAdjust(id, name, stock) {
        document.getElementById('adjName').textContent = name;
        document.getElementById('adjStock').textContent = stock;
        document.getElementById('adjustForm').action = '{{ url('inventory') }}/' + id + '/adjust';
        SS.openModal('adjustModal');
    }
</script>
@endpush
@endcan
@endsection
