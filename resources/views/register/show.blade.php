@extends('layouts.app')
@section('title', 'Z-Report')
@section('crumb', 'Register session #'.$session->id)

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Register Session') }} #{{ $session->id }}</h2><p>{{ $session->user->name ?? '' }} · {{ $session->branch?->branch_number }} {{ $session->branch?->name }} · {{ $session->opened_at?->translatedFormat('d M Y') }}</p></div>
    <div class="actions">
        <a href="{{ route('register.index') }}" class="btn btn-light">@include('partials.icon',['name'=>'arrow-left']) {{ __('Back') }}</a>
        <button class="btn btn-light" onclick="window.print()">@include('partials.icon',['name'=>'printer']) {{ __('Print Z-Report') }}</button>
    </div>
</div>

<div class="grid" style="grid-template-columns: 1fr 1fr; align-items:start">
    <div class="card card-pad">
        <h3 style="margin-bottom:16px">{{ __('Cash Summary') }}</h3>
        @php $row = fn($l,$v,$cls='') => '<div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--line-2)"><span class="text-muted">'.$l.'</span><span class="t-mono fw-700 '.$cls.'">'.$v.'</span></div>'; @endphp
        {!! $row(__('Opening float'), money($session->opening_float)) !!}
        {!! $row(__('Cash sales'), money($session->cash_sales), 'text-green') !!}
        {!! $row(__('Cash refunds'), '− '.money($session->cash_refunds), 'text-red') !!}
        {!! $row(__('Expected in drawer'), money($session->expected_cash), 'text-blue') !!}
        @if($session->status=='closed')
            {!! $row(__('Counted cash'), money($session->counted_cash)) !!}
            <div style="display:flex;justify-content:space-between;padding-top:12px;margin-top:6px;border-top:2px dashed var(--line);font-size:18px;font-weight:800"><span>{{ __('Difference') }}</span><span class="t-mono {{ $session->difference<0?'text-red':($session->difference>0?'text-green':'') }}">{{ money($session->difference) }}</span></div>
            @if($session->difference != 0)
                <div class="alert {{ $session->difference<0?'alert-error':'alert-success' }}" style="margin:14px 0 0">@include('partials.icon',['name'=>$session->difference<0?'alert':'check-circle']) {{ $session->difference<0?__('The cash drawer is short by :amount', ['amount' => money(abs($session->difference))]):__('The cash drawer is over by :amount', ['amount' => money(abs($session->difference))]) }}</div>
            @else
                <div class="alert alert-success" style="margin:14px 0 0">@include('partials.icon',['name'=>'check-circle']) {{ __('Drawer balanced perfectly.') }}</div>
            @endif
        @else
            <div class="alert alert-info" style="margin:14px 0 0">@include('partials.icon',['name'=>'info']) {{ __('Session is still open.') }}</div>
        @endif
    </div>

    <div class="card card-pad">
        <h3 style="margin-bottom:16px">{{ __('Sales Breakdown') }}</h3>
        {!! $row(__('Cash'), money($session->cash_sales)) !!}
        {!! $row(__('Card'), money($session->card_sales)) !!}
        {!! $row(__('Mobile / other'), money($session->other_sales)) !!}
        <div style="display:flex;justify-content:space-between;padding-top:12px;margin-top:6px;border-top:2px dashed var(--line);font-size:18px;font-weight:800"><span>{{ __('Total sales') }}</span><span class="t-mono text-blue">{{ money($session->cash_sales + $session->card_sales + $session->other_sales) }}</span></div>
        <div style="display:flex;justify-content:space-between;padding:12px 0 0"><span class="text-muted">{{ __('Transactions') }}</span><span class="fw-700">{{ $session->orders_count }}</span></div>
        <div style="display:flex;justify-content:space-between"><span class="text-muted">{{ __('Opened') }}</span><span>{{ $session->opened_at?->translatedFormat('d M, h:i A') }}</span></div>
        <div style="display:flex;justify-content:space-between"><span class="text-muted">{{ __('Closed') }}</span><span>{{ $session->closed_at?->translatedFormat('d M, h:i A') ?? '—' }}</span></div>
        @if($session->note)<div class="mt-2" style="padding-top:12px;border-top:1px solid var(--line-2)"><div class="text-muted fs-sm">{{ __('Note') }}</div>{{ $session->note }}</div>@endif
    </div>
</div>
@endsection
