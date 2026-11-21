@extends('layouts.app')
@section('title', 'Sales Report')
@section('crumb', 'Revenue and transactions')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Sales Report') }}</h2><p>{{ $from->translatedFormat('d M Y') }} — {{ $to->translatedFormat('d M Y') }}</p></div>
    <form method="GET" class="actions" action="{{ route('reports.sales') }}">
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input" style="width:auto">
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input" style="width:auto">
        @if(auth()->user()->hasRole('Super Admin'))
        <select name="branch_id" class="select" style="width:auto">
            <option value="all" @selected($selectedBranch === 'all')>{{ __('All Branches') }}</option>
            @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) $selectedBranch === (string) $branch->id)>{{ $branch->branch_number }} · {{ $branch->name }}</option>@endforeach
        </select>
        @endif
        <button class="btn btn-primary">{{ __('Apply') }}</button>
        <a class="btn btn-light" href="{{ route('reports.sales.export', array_merge(request()->query(), ['format' => 'csv'])) }}">{{ __('CSV') }}</a>
        <a class="btn btn-light" href="{{ route('reports.sales.export', array_merge(request()->query(), ['format' => 'xls'])) }}">{{ __('Excel') }}</a>
    </form>
</div>

<div class="grid grid-4 mb-2">
    <div class="card card-pad stat"><div class="ico ico-blue">@include('partials.icon',['name'=>'receipt'])</div><div class="label">{{ __('Orders') }}</div><div class="value">{{ number_format($summary['orders']) }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-green">@include('partials.icon',['name'=>'money'])</div><div class="label">{{ __('Revenue') }}</div><div class="value">{{ money($summary['revenue']) }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-violet">@include('partials.icon',['name'=>'chart'])</div><div class="label">{{ __('Avg. Order') }}</div><div class="value">{{ money($summary['avg']) }}</div></div>
    <div class="card card-pad stat"><div class="ico ico-amber">@include('partials.icon',['name'=>'tag'])</div><div class="label">{{ __('Discounts') }}</div><div class="value">{{ money($summary['discount']) }}</div></div>
</div>

<div class="grid grid-2 mb-2">
    <div class="card">
        <div class="card-head"><h3>{{ __('By Payment Method') }}</h3></div>
        <div class="table-wrap"><table class="data"><thead><tr><th>{{ __('Method') }}</th><th class="t-right">{{ __('Orders') }}</th><th class="t-right">{{ __('Revenue') }}</th></tr></thead><tbody>
            @forelse($byPayment as $p)<tr><td><span class="badge badge-gray">{{ __(ucfirst($p->payment_method)) }}</span></td><td class="t-right">{{ $p->orders }}</td><td class="t-right t-mono fw-700">{{ money($p->revenue) }}</td></tr>
            @empty<tr><td colspan="3"><div class="empty" style="padding:24px">{{ __('No data') }}</div></td></tr>@endforelse
        </tbody></table></div>
    </div>
    <div class="card">
        <div class="card-head"><h3>{{ __('By Cashier') }}</h3></div>
        <div class="table-wrap"><table class="data"><thead><tr><th>{{ __('Cashier') }}</th><th class="t-right">{{ __('Orders') }}</th><th class="t-right">{{ __('Revenue') }}</th></tr></thead><tbody>
            @forelse($byCashier as $c)<tr><td class="t-title">{{ $c->cashier }}</td><td class="t-right">{{ $c->orders }}</td><td class="t-right t-mono fw-700">{{ money($c->revenue) }}</td></tr>
            @empty<tr><td colspan="3"><div class="empty" style="padding:24px">{{ __('No data') }}</div></td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Daily Breakdown') }}</h3></div>
    <div class="table-wrap"><table class="data"><thead><tr><th>{{ __('Date') }}</th><th class="t-right">{{ __('Orders') }}</th><th class="t-right">{{ __('Revenue') }}</th></tr></thead><tbody>
        @forelse($byDay as $d)<tr><td>{{ \Illuminate\Support\Carbon::parse($d->d)->translatedFormat('D, d M Y') }}</td><td class="t-right">{{ $d->orders }}</td><td class="t-right t-mono fw-700">{{ money($d->revenue) }}</td></tr>
        @empty<tr><td colspan="3"><div class="empty">@include('partials.icon',['name'=>'chart'])<h4>{{ __('No sales in this period') }}</h4></div></td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection
