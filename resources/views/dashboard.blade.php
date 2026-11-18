@extends('layouts.app')
@section('title', 'Dashboard')
@section('crumb', 'Overview of your store performance')

@section('content')
@php $max = max(1, $chart->max('value')); @endphp

<div class="grid grid-4 mb-2">
    <div class="card card-pad stat">
        <div class="ico ico-green">@include('partials.icon',['name'=>'money'])</div>
        <div class="label">{{ __("Today's Revenue") }}</div>
        <div class="value">{{ money($todayRevenue) }}</div>
        <div class="foot">{{ $todayOrders }} {{ __('orders today') }}</div>
    </div>
    <div class="card card-pad stat">
        <div class="ico ico-blue">@include('partials.icon',['name'=>'chart'])</div>
        <div class="label">{{ __('This Month') }}</div>
        <div class="value">{{ money($monthRevenue) }}</div>
        <div class="foot">{{ __('Gross sales') }}, {{ now()->translatedFormat('M Y') }}</div>
    </div>
    <div class="card card-pad stat">
        <div class="ico ico-violet">@include('partials.icon',['name'=>'trend'])</div>
        <div class="label">{{ __('Month Profit') }}</div>
        <div class="value">{{ money($monthProfit) }}</div>
        <div class="foot">{{ __('Gross margin this month') }}</div>
    </div>
    <div class="card card-pad stat">
        <div class="ico ico-amber">@include('partials.icon',['name'=>'box'])</div>
        <div class="label">{{ __('Active Products') }}</div>
        <div class="value">{{ number_format($productCount) }}</div>
        <div class="foot">
            @if($lowStockCount)<span class="trend-down">{{ $lowStockCount }} {{ __('low on stock') }}</span>@else {{ __('All well stocked') }} @endif
        </div>
    </div>
</div>

<div class="grid dashboard-content-grid">
    <div class="card dashboard-revenue-card" data-dashboard-revenue-chart>
        <div class="card-head dashboard-chart-head">
            <div class="dashboard-chart-heading">
                <h3 id="dashboardRevenueTitle">{{ __('Revenue — last 7 days') }}</h3>
                <div class="sub" id="dashboardRevenueSubtitle">{{ __('Daily completed sales') }}</div>
            </div>
            <div class="dashboard-chart-tools">
                <div class="dashboard-chart-switch" role="group" aria-label="{{ __('Chart type') }}">
                    <button class="active" type="button" data-chart-mode="bars" aria-pressed="true">{{ __('Bar chart') }}</button>
                    <button type="button" data-chart-mode="line" aria-pressed="false">{{ __('Line chart') }}</button>
                </div>
                <div class="dashboard-period-switch" id="dashboardPeriodSwitch" role="group" aria-label="{{ __('Time period') }}" hidden>
                    <button class="active" type="button" data-chart-period="daily" aria-pressed="true">{{ __('Daily') }}</button>
                    <button type="button" data-chart-period="weekly" aria-pressed="false">{{ __('Weekly') }}</button>
                </div>
                <div class="badge badge-blue">{{ __('Total') }} <span id="dashboardRevenueTotal">{{ money($chart->sum('value')) }}</span></div>
            </div>
        </div>
        <div class="card-pad dashboard-chart-panel" data-chart-panel="bars">
            <div class="bars">
                @foreach($chart as $c)
                    <div class="bar-col">
                        <span class="bar-val">{{ $c['value'] > 0 ? money($c['value']) : '—' }}</span>
                        <div class="bar" style="height: {{ max(4, ($c['value']/$max)*100) }}%" title="{{ $c['date'] }}: {{ money($c['value']) }}"></div>
                        <span class="bar-lbl">{{ $c['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-pad dashboard-chart-panel dashboard-line-panel" data-chart-panel="line" hidden>
            <div class="dashboard-line-chart-wrap">
                <svg id="dashboardRevenueLine" class="dashboard-revenue-line" viewBox="0 0 760 300" role="img"></svg>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3>{{ __('Top Products') }}</h3><span class="sub" style="margin-inline-start:auto">{{ __('This month') }}</span></div>
        <div class="card-pad" style="padding-top:8px">
            @forelse($topProducts as $i => $p)
                <div class="flex items-center gap-3" style="padding:9px 0;border-bottom:1px solid var(--line-2)">
                    <div class="avatar" style="width:30px;height:30px;font-size:12px;background:var(--blue-100);color:var(--blue-700)">{{ $i+1 }}</div>
                    <div style="flex:1;min-width:0">
                        <div class="t-title" style="font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $p->name }}</div>
                        <div class="t-sub">{{ rtrim(rtrim(number_format($p->qty,2),'0'),'.') }} {{ __('sold') }}</div>
                    </div>
                    <div class="fw-700 text-blue mono">{{ money($p->revenue) }}</div>
                </div>
            @empty
                <div class="empty" style="padding:30px">@include('partials.icon',['name'=>'star'])<h4>{{ __('No sales yet') }}</h4></div>
            @endforelse
        </div>
    </div>
</div>

<div class="grid dashboard-content-grid" style="margin-top:18px">
    <div class="card">
        <div class="card-head">
            <h3>{{ __('Recent Sales') }}</h3>
            <a href="{{ route('sales.index') }}" class="btn btn-soft btn-sm" style="margin-inline-start:auto">{{ __('View all') }}</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Cashier') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Payment') }}</th><th class="t-right">{{ __('Total') }}</th></tr></thead>
                <tbody>
                @forelse($recentSales as $sale)
                    <tr onclick="location='{{ route('sales.show',$sale) }}'" style="cursor:pointer">
                        <td><span class="t-title">{{ $sale->invoice_no }}</span><div class="t-sub">{{ $sale->created_at->diffForHumans() }}</div></td>
                        <td>{{ $sale->cashier->name ?? '—' }}</td>
                        <td>{{ $sale->customer->name ?? 'Walk-in' }}</td>
                        <td><span class="badge badge-gray">{{ __(ucfirst($sale->payment_method)) }}</span></td>
                        <td class="t-right fw-700 mono">{{ money($sale->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty">@include('partials.icon',['name'=>'receipt'])<h4>{{ __('No sales recorded yet') }}</h4><p>{{ __('Open the POS to start selling.') }}</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>{{ __('Low Stock Alerts') }}</h3>
            @can('inventory.view')<a href="{{ route('inventory.index',['filter'=>'low']) }}" class="btn btn-soft btn-sm" style="margin-inline-start:auto">{{ __('Manage') }}</a>@endcan
        </div>
        <div class="card-pad" style="padding-top:8px">
            @forelse($lowStock as $p)
                <div class="flex items-center gap-3" style="padding:9px 0;border-bottom:1px solid var(--line-2)">
                    <div class="ico {{ $p->stock_quantity <= 0 ? 'ico-red' : 'ico-amber' }}" style="width:34px;height:34px;position:static">@include('partials.icon',['name'=>'box'])</div>
                    <div style="flex:1;min-width:0">
                        <div class="t-title" style="font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $p->name }}</div>
                        <div class="t-sub">{{ __('Alert at') }} {{ rtrim(rtrim(number_format($p->alert_quantity,2),'0'),'.') }} {{ $p->unit->short_name ?? '' }}</div>
                    </div>
                    <span class="badge {{ $p->stock_quantity <= 0 ? 'badge-red' : 'badge-amber' }}">{{ rtrim(rtrim(number_format($p->stock_quantity,2),'0'),'.') }} {{ __('left') }}</span>
                </div>
            @empty
                <div class="empty" style="padding:30px">@include('partials.icon',['name'=>'check-circle'])<h4>{{ __('All stocked up') }}</h4><p>{{ __('No low-stock items right now.') }}</p></div>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.DASHBOARD_CHART_DATA = {
        daily: @json($chart->values()),
        weekly: @json($weeklyChart->values()),
        currencySymbol: @json(setting('currency_symbol', '$')),
        currencyPosition: @json(setting('currency_position', 'before')),
        labels: {
            barTitle: @json(__('Revenue — last 7 days')),
            dailyTitle: @json(__('Daily revenue trend')),
            weeklyTitle: @json(__('Weekly revenue trend')),
            dailySubtitle: @json(__('Daily completed sales')),
            weeklySubtitle: @json(__('Weekly completed sales')),
            svgTitle: @json(__('Revenue trend chart')),
        },
    };
</script>
<script src="{{ asset('js/dashboard.js') }}" defer></script>
@endpush
@endsection
