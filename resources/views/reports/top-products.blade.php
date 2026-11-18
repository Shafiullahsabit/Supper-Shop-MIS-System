@extends('layouts.app')
@section('title', 'Top Products')
@section('crumb', 'Best sellers')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Top Products') }}</h2><p>{{ $from->translatedFormat('d M Y') }} — {{ $to->translatedFormat('d M Y') }}</p></div>
    <form method="GET" class="actions">
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input" style="width:auto">
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input" style="width:auto">
        <button class="btn btn-primary">{{ __('Apply') }}</button>
    </form>
</div>

<div class="card">
    <div class="card-head"><h3>{{ __('Best Sellers by Quantity') }}</h3></div>
    <div class="card-pad">
        @php $maxQty = max(1, $top->max('qty')); @endphp
        @forelse($top as $i => $p)
            <div class="flex items-center gap-3" style="padding:11px 0;border-bottom:1px solid var(--line-2)">
                <div class="avatar" style="width:30px;height:30px;font-size:12px;background:var(--blue-100);color:var(--blue-700)">{{ $i+1 }}</div>
                <div style="flex:1;min-width:0">
                    <div class="flex justify-between" style="margin-bottom:6px"><span class="t-title">{{ $p->name }}</span><span class="text-muted fs-sm">{{ rtrim(rtrim(number_format($p->qty,3),'0'),'.') }} {{ __('sold') }} · {{ money($p->revenue) }}</span></div>
                    <div class="progress"><span style="width:{{ ($p->qty/$maxQty)*100 }}%"></span></div>
                </div>
            </div>
        @empty
            <div class="empty">@include('partials.icon',['name'=>'star'])<h4>{{ __('No sales in this period') }}</h4></div>
        @endforelse
    </div>
</div>
@endsection
