@extends('layouts.app')
@section('title', 'Invoice '.$sale->invoice_no)
@section('crumb', 'Sale details')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ $sale->invoice_no }}</h2><p>{{ $sale->created_at->translatedFormat('l, d M Y · h:i A') }}</p></div>
    <div class="actions">
        <a href="{{ route('sales.index') }}" class="btn btn-light">@include('partials.icon',['name'=>'arrow-left']) {{ __('Back') }}</a>
        <a href="{{ route('pos.receipt',$sale) }}" target="_blank" class="btn btn-light">@include('partials.icon',['name'=>'printer']) {{ __('Receipt') }}</a>
        @can('sales.refund')
            @if($sale->status !== 'refunded')
                <button class="btn btn-ghost-danger" onclick="SS.openModal('refundModal')">@include('partials.icon',['name'=>'refresh']) {{ __('Refund') }}</button>
            @endif
        @endcan
    </div>
</div>

@if($sale->status==='refunded')
    <div class="alert alert-error">@include('partials.icon',['name'=>'alert']) {{ __('This sale has been refunded and stock was restored.') }}</div>
@endif

<div class="grid" style="grid-template-columns: 2fr 1fr; align-items:start">
    <div class="card">
        <div class="card-head"><h3>{{ __('Items') }}</h3><span class="badge badge-blue" style="margin-inline-start:auto">{{ $sale->items->count() }} {{ __('lines') }}</span></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>{{ __('Product') }}</th><th class="t-right">{{ __('Price') }}</th><th class="t-right">{{ __('Qty') }}</th><th class="t-right">{{ __('Tax') }}</th><th class="t-right">{{ __('Subtotal') }}</th></tr></thead>
                <tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td class="t-title">{{ $item->name }}</td>
                        <td class="t-right t-mono">{{ money($item->price) }}</td>
                        <td class="t-right t-mono">{{ rtrim(rtrim(number_format($item->quantity,3),'0'),'.') }}</td>
                        <td class="t-right t-mono">{{ money($item->tax) }}</td>
                        <td class="t-right t-mono fw-700">{{ money($item->subtotal) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:18px">
        <div class="card card-pad">
            <h3 style="margin-bottom:14px">{{ __('Summary') }}</h3>
            <div class="tot-row" style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Subtotal') }}</span><span class="t-mono">{{ money($sale->subtotal) }}</span></div>
            <div class="tot-row" style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Discount') }}</span><span class="t-mono">− {{ money($sale->discount) }}</span></div>
            <div class="tot-row" style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Tax') }}</span><span class="t-mono">{{ money($sale->tax) }}</span></div>
            <div style="display:flex;justify-content:space-between;padding-top:12px;margin-top:6px;border-top:2px dashed var(--line);font-size:20px;font-weight:800"><span>{{ __('Total') }}</span><span class="text-blue t-mono">{{ money($sale->total) }}</span></div>
            <div class="tot-row" style="display:flex;justify-content:space-between;margin-top:12px"><span class="text-muted">{{ __('Paid') }} ({{ __(ucfirst($sale->payment_method)) }})</span><span class="t-mono">{{ money($sale->paid) }}</span></div>
            @if($sale->change_due>0)<div class="tot-row" style="display:flex;justify-content:space-between"><span class="text-muted">{{ __('Change') }}</span><span class="t-mono">{{ money($sale->change_due) }}</span></div>@endif
        </div>
        <div class="card card-pad">
            <h3 style="margin-bottom:14px">{{ __('Details') }}</h3>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Cashier') }}</span><span class="fw-700">{{ $sale->cashier->name ?? '—' }}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Customer') }}</span><span class="fw-700">{{ $sale->customer->name ?? __('Walk-in') }}</span></div>
            <div style="display:flex;justify-content:space-between"><span class="text-muted">{{ __('Status') }}</span>
                @if($sale->status=='completed')<span class="badge badge-green">{{ __('Completed') }}</span>@else<span class="badge badge-red">{{ __(ucfirst(str_replace('_',' ',$sale->status))) }}</span>@endif
            </div>
            @if($sale->note)<div class="mt-2" style="padding-top:12px;border-top:1px solid var(--line-2)"><div class="text-muted fs-sm">{{ __('Note') }}</div>{{ $sale->note }}</div>@endif
        </div>
    </div>
</div>

@can('sales.refund')
@if($sale->status !== 'refunded')
<div class="modal-overlay" id="refundModal" style="display:none">
    <form class="modal" method="POST" action="{{ route('sales.refund',$sale) }}">
        @csrf
        <div class="modal-head"><h3>{{ __('Refund Sale') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('refundModal')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="alert alert-error" style="margin-bottom:16px">@include('partials.icon',['name'=>'alert']) {{ __('This will restore stock for all items and mark the sale as refunded.') }}</div>
            <div class="field" style="margin:0"><label>{{ __('Reason (optional)') }}</label><textarea class="input" name="reason" placeholder="{{ __('e.g. Customer returned items') }}"></textarea></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('refundModal')">{{ __('Cancel') }}</button><button class="btn btn-danger">{{ __('Confirm Refund') }}</button></div>
    </form>
</div>
@endif
@endcan
@endsection
