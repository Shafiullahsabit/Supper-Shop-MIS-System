@extends('layouts.app')
@section('title', 'Profit Report')
@section('crumb', 'Margins and profitability')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Profit Report') }}</h2><p>{{ $from->translatedFormat('d M Y') }} — {{ $to->translatedFormat('d M Y') }}</p></div>
    <form method="GET" class="actions">
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input" style="width:auto">
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input" style="width:auto">
        <button class="btn btn-primary">{{ __('Apply') }}</button>
    </form>
</div>

<div class="grid grid-4 mb-2">
    <div class="card card-pad stat"><div class="ico ico-blue">@include('partials.icon',['name'=>'money'])</div><div class="label">{{ __('Revenue') }}</div><div class="value">{{ money($revenue) }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-amber">@include('partials.icon',['name'=>'box'])</div><div class="label">{{ __('Cost of Goods') }}</div><div class="value">{{ money($cost) }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-green">@include('partials.icon',['name'=>'trend'])</div><div class="label">{{ __('Gross Profit') }}</div><div class="value {{ $profit>=0?'text-green':'text-red' }}">{{ money($profit) }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-violet">@include('partials.icon',['name'=>'chart'])</div><div class="label">{{ __('Margin') }}</div><div class="value">{{ number_format($margin,1) }}%</div></div>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Profit by Product') }}</h3></div>
    <div class="table-wrap"><table class="data">
        <thead><tr><th>{{ __('Product') }}</th><th class="t-right">{{ __('Qty Sold') }}</th><th class="t-right">{{ __('Revenue') }}</th><th class="t-right">{{ __('Cost') }}</th><th class="t-right">{{ __('Profit') }}</th><th class="t-right">{{ __('Margin') }}</th></tr></thead>
        <tbody>
        @forelse($byProduct as $p)
            @php $m = $p->revenue>0 ? ($p->profit/$p->revenue)*100 : 0; @endphp
            <tr>
                <td class="t-title">{{ $p->name }}</td>
                <td class="t-right t-mono">{{ rtrim(rtrim(number_format($p->qty,3),'0'),'.') }}</td>
                <td class="t-right t-mono">{{ money($p->revenue) }}</td>
                <td class="t-right t-mono">{{ money($p->cost) }}</td>
                <td class="t-right t-mono fw-700 {{ $p->profit>=0?'text-green':'text-red' }}">{{ money($p->profit) }}</td>
                <td class="t-right"><span class="badge {{ $m>=25?'badge-green':($m>=10?'badge-amber':'badge-red') }}">{{ number_format($m,1) }}%</span></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty">@include('partials.icon',['name'=>'trend'])<h4>{{ __('No sales in this period') }}</h4></div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
