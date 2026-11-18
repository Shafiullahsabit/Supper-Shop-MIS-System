@extends('layouts.app')
@section('title', 'Stock Report')
@section('crumb', 'Inventory valuation')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Stock Report') }}</h2><p>{{ __('Current inventory valuation') }}</p></div>
    <div class="actions"><button class="btn btn-light" onclick="window.print()">@include('partials.icon',['name'=>'printer']) {{ __('Print') }}</button></div>
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
        <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Category') }}</th><th class="t-right">{{ __('Stock') }}</th><th class="t-right">{{ __('Cost') }}</th><th class="t-right">{{ __('Retail') }}</th><th class="t-right">{{ __('Stock Value') }}</th></tr></thead>
        <tbody>
        @foreach($products as $p)
            <tr>
                <td class="t-title">{{ $p->name }}<div class="t-sub t-mono">{{ $p->sku }}</div></td>
                <td>{{ $p->category->name ?? '—' }}</td>
                <td class="t-right t-mono {{ $p->stock_status=='out'?'text-red':($p->stock_status=='low'?'':'') }}">{{ rtrim(rtrim(number_format($p->stock_quantity,3),'0'),'.') }} {{ $p->unit->short_name ?? '' }}</td>
                <td class="t-right t-mono">{{ money($p->cost_price) }}</td>
                <td class="t-right t-mono">{{ money($p->sale_price) }}</td>
                <td class="t-right t-mono fw-700">{{ money($p->cost_price * $p->stock_quantity) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endsection
