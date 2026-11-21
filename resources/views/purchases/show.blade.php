@extends('layouts.app')
@section('title', 'Purchase '.$purchase->reference)
@section('crumb', 'Purchase details')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ $purchase->reference }}</h2><p>{{ $purchase->purchase_date->translatedFormat('l, d M Y') }}</p></div>
    <div class="actions"><a href="{{ route('purchases.index') }}" class="btn btn-light">@include('partials.icon',['name'=>'arrow-left']) {{ __('Back') }}</a></div>
</div>

<div class="grid" style="grid-template-columns: 2fr 1fr; align-items:start">
    <div class="card">
        <div class="card-head"><h3>{{ __('Items received') }}</h3><span class="badge badge-blue" style="margin-inline-start:auto">{{ $purchase->items->count() }} {{ __('items') }}</span></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>{{ __('Product') }}</th><th class="t-right">{{ __('Cost') }}</th><th class="t-right">{{ __('Qty') }}</th><th class="t-right">{{ __('Subtotal') }}</th></tr></thead>
                <tbody>
                @foreach($purchase->items as $item)
                    <tr><td class="t-title">{{ $item->product->name ?? '—' }}</td>
                        <td class="t-right t-mono">{{ money($item->cost) }}</td>
                        <td class="t-right t-mono">{{ rtrim(rtrim(number_format($item->quantity,3),'0'),'.') }}</td>
                        <td class="t-right t-mono fw-700">{{ money($item->subtotal) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:18px">
        <div class="card card-pad">
            <h3 style="margin-bottom:14px">{{ __('Summary') }}</h3>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Subtotal') }}</span><span class="t-mono">{{ money($purchase->subtotal) }}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Discount') }}</span><span class="t-mono">− {{ money($purchase->discount) }}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Tax') }}</span><span class="t-mono">{{ money($purchase->tax) }}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Shipping') }}</span><span class="t-mono">{{ money($purchase->shipping) }}</span></div>
            <div style="display:flex;justify-content:space-between;padding-top:12px;border-top:2px dashed var(--line);font-size:19px;font-weight:800"><span>{{ __('Total') }}</span><span class="text-blue t-mono">{{ money($purchase->total) }}</span></div>
            <div style="display:flex;justify-content:space-between;margin-top:12px"><span class="text-muted">{{ __('Paid') }}</span><span class="t-mono">{{ money($purchase->paid) }}</span></div>
            <div style="display:flex;justify-content:space-between"><span class="text-muted">{{ __('Balance due') }}</span><span class="t-mono fw-700 text-red">{{ money(max(0,$purchase->total - $purchase->paid)) }}</span></div>
        </div>
        <div class="card">
            <div class="card-head"><h3>{{ __('Payment History') }}</h3></div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th>{{ __('Recorded by') }}</th><th class="t-right">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                    @forelse($purchase->payments as $payment)
                        <tr>
                            <td>{{ $payment->paid_at->translatedFormat('d M Y') }}</td>
                            <td>{{ $payment->payment_method ? __(ucfirst($payment->payment_method)) : __('Existing payment') }}</td>
                            <td>{{ $payment->user?->name ?? '—' }}</td>
                            <td class="t-right t-mono fw-700">{{ money($payment->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty" style="padding:20px">{{ __('No payment records') }}</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @can('purchases.manage')
                @if($purchase->outstanding > 0)
                <form method="POST" action="{{ route('purchases.payments.store',$purchase) }}" class="card-pad">
                    @csrf
                    <h4 style="margin-bottom:12px">{{ __('Record Supplier Payment') }}</h4>
                    <div class="field"><label>{{ __('Amount') }}</label><input class="input t-mono" type="number" step="0.01" min="0.01" max="{{ $purchase->outstanding }}" name="amount" required></div>
                    <div class="form-row">
                        <div class="field"><label>{{ __('Payment method') }}</label>
                            <select class="select" name="payment_method">@foreach(['cash','card','mobile','bank','other'] as $method)<option value="{{ $method }}">{{ __(ucfirst($method)) }}</option>@endforeach</select>
                        </div>
                        <div class="field"><label>{{ __('Payment date') }}</label><input class="input" type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}" required></div>
                    </div>
                    <div class="field"><label>{{ __('Note') }}</label><textarea class="input" name="note"></textarea></div>
                    <button class="btn btn-primary btn-block">{{ __('Record Payment') }}</button>
                </form>
                @endif
            @endcan
        </div>
        <div class="card card-pad">
            <h3 style="margin-bottom:14px">{{ __('Details') }}</h3>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Branch') }}</span><span class="fw-700">{{ $purchase->branch?->branch_number }} · {{ $purchase->branch?->name }}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Supplier') }}</span><span class="fw-700">{{ $purchase->supplier->name ?? '—' }}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span class="text-muted">{{ __('Recorded by') }}</span><span class="fw-700">{{ $purchase->user->name ?? '—' }}</span></div>
            <div style="display:flex;justify-content:space-between"><span class="text-muted">{{ __('Status') }}</span>
                @if($purchase->status=='received')<span class="badge badge-green">{{ __('Received') }}</span>@elseif($purchase->status=='ordered')<span class="badge badge-blue">{{ __('Ordered') }}</span>@else<span class="badge badge-amber">{{ __('Pending') }}</span>@endif</div>
            @if($purchase->note)<div class="mt-2" style="padding-top:12px;border-top:1px solid var(--line-2)"><div class="text-muted fs-sm">{{ __('Note') }}</div>{{ $purchase->note }}</div>@endif
        </div>
    </div>
</div>
@endsection
