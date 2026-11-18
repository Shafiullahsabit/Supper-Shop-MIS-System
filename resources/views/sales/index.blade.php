@extends('layouts.app')
@section('title', 'Sales')
@section('crumb', 'Sales history and invoices')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Sales') }}</h2><p>{{ $totals['count'] }} {{ __('transactions') }}</p></div>
</div>

<form method="GET" class="toolbar">
    <div class="search-box">@include('partials.icon',['name'=>'search'])<input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search invoice #…') }}"></div>
    <input type="date" name="from" value="{{ request('from') }}" class="input" style="width:auto">
    <input type="date" name="to" value="{{ request('to') }}" class="input" style="width:auto">
    <select name="payment" class="select" style="width:auto"><option value="">{{ __('All payments') }}</option>
        @foreach(['cash','card','mobile'] as $pm)<option value="{{ $pm }}" @selected(request('payment')==$pm)>{{ __(ucfirst($pm)) }}</option>@endforeach</select>
    <select name="status" class="select" style="width:auto"><option value="">{{ __('All status') }}</option>
        <option value="completed" @selected(request('status')=='completed')>{{ __('Completed') }}</option>
        <option value="refunded" @selected(request('status')=='refunded')>{{ __('Refunded') }}</option></select>
    <button class="btn btn-light">{{ __('Filter') }}</button>
    @if(request()->hasAny(['q','from','to','payment','status']))<a href="{{ route('sales.index') }}" class="btn btn-light">{{ __('Reset') }}</a>@endif
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th>@if(auth()->user()->hasRole('Super Admin'))<th>{{ __('Branch') }}</th>@endif<th>{{ __('Cashier') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Status') }}</th><th class="t-right">{{ __('Total') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($sales as $sale)
                <tr onclick="location='{{ route('sales.show',$sale) }}'" style="cursor:pointer">
                    <td class="t-title t-mono">{{ $sale->invoice_no }}</td>
                    <td>{{ $sale->created_at->translatedFormat('d M, h:i A') }}</td>
                    @if(auth()->user()->hasRole('Super Admin'))<td>{{ $sale->branch?->branch_number }} · {{ $sale->branch?->name }}</td>@endif
                    <td>{{ $sale->cashier->name ?? '—' }}</td>
                    <td>{{ $sale->customer->name ?? __('Walk-in') }}</td>
                    <td><span class="badge badge-gray">{{ __(ucfirst($sale->payment_method)) }}</span></td>
                    <td>@if($sale->status=='completed')<span class="badge badge-green">{{ __('Completed') }}</span>@else<span class="badge badge-red">{{ __(ucfirst(str_replace('_',' ',$sale->status))) }}</span>@endif</td>
                    <td class="t-right t-mono fw-700">{{ money($sale->total) }}</td>
                    <td onclick="event.stopPropagation()"><a href="{{ route('pos.receipt',$sale) }}" target="_blank" class="icon-btn" title="{{ __('Receipt') }}">@include('partials.icon',['name'=>'printer'])</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ auth()->user()->hasRole('Super Admin') ? 9 : 8 }}"><div class="empty">@include('partials.icon',['name'=>'receipt'])<h4>{{ __('No sales found') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $sales->links('partials.pagination') }}</div>
</div>
@endsection
