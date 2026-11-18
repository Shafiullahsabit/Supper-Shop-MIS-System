@extends('layouts.app')
@section('title', $customer->name)
@section('crumb', 'Customer profile')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ $customer->name }}</h2><p>{{ __('Customer since') }} {{ $customer->created_at->translatedFormat('M Y') }}</p></div>
    <div class="actions"><a href="{{ route('customers.index') }}" class="btn btn-light">@include('partials.icon',['name'=>'arrow-left']) {{ __('Back') }}</a></div>
</div>

<div class="grid grid-4 mb-2">
    <div class="card card-pad stat"><div class="ico ico-blue">@include('partials.icon',['name'=>'receipt'])</div><div class="label">{{ __('Total Orders') }}</div><div class="value">{{ $customer->sales->count() }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-green">@include('partials.icon',['name'=>'money'])</div><div class="label">{{ __('Total Spent') }}</div><div class="value">{{ money($customer->total_spent) }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-violet">@include('partials.icon',['name'=>'star'])</div><div class="label">{{ __('Loyalty Points') }}</div><div class="value">{{ $customer->loyalty_points }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-amber">@include('partials.icon',['name'=>'phone'])</div><div class="label">{{ __('Contact') }}</div><div class="value" style="font-size:17px">{{ $customer->phone ?? '—' }}</div><div class="foot">{{ $customer->email ?? __('no email') }}</div></div>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Purchase History') }}</h3></div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Status') }}</th><th class="t-right">{{ __('Total') }}</th></tr></thead>
            <tbody>
            @forelse($customer->sales as $sale)
                <tr onclick="location='{{ route('sales.show',$sale) }}'" style="cursor:pointer">
                    <td class="t-title t-mono">{{ $sale->invoice_no }}</td>
                    <td>{{ $sale->created_at->translatedFormat('d M Y, h:i A') }}</td>
                    <td><span class="badge badge-gray">{{ __(ucfirst($sale->payment_method)) }}</span></td>
                    <td>@if($sale->status=='completed')<span class="badge badge-green">{{ __('Completed') }}</span>@else<span class="badge badge-red">{{ __(ucfirst(str_replace('_',' ',$sale->status))) }}</span>@endif</td>
                    <td class="t-right t-mono fw-700">{{ money($sale->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty">@include('partials.icon',['name'=>'receipt'])<h4>{{ __('No purchases yet') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
