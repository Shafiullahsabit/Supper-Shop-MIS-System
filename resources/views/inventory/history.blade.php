@extends('layouts.app')
@section('title', 'Adjustment History')
@section('crumb', 'Stock adjustment log')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Adjustment History') }}</h2><p>{{ __('All manual stock changes') }}</p></div>
    <div class="actions"><a href="{{ route('inventory.index') }}" class="btn btn-light">@include('partials.icon',['name'=>'arrow-left']) {{ __('Back') }}</a></div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Product') }}</th><th>{{ __('Type') }}</th><th class="t-right">{{ __('Qty') }}</th><th class="t-right">{{ __('Before → After') }}</th><th>{{ __('Reason') }}</th><th>{{ __('By') }}</th></tr></thead>
            <tbody>
            @forelse($adjustments as $a)
                <tr>
                    <td>{{ $a->created_at->translatedFormat('d M Y, h:i A') }}</td>
                    <td class="t-title">{{ $a->product->name ?? '—' }}</td>
                    <td>@if($a->type=='add')<span class="badge badge-green">+ {{ __('Added') }}</span>@else<span class="badge badge-red">− {{ __('Removed') }}</span>@endif</td>
                    <td class="t-right t-mono fw-700">{{ rtrim(rtrim(number_format($a->quantity,3),'0'),'.') }}</td>
                    <td class="t-right t-mono">{{ rtrim(rtrim(number_format($a->stock_before,3),'0'),'.') }} → {{ rtrim(rtrim(number_format($a->stock_after,3),'0'),'.') }}</td>
                    <td><span class="badge badge-gray">{{ __(ucfirst(str_replace('_', ' ', $a->reason))) }}</span>@if($a->note)<div class="t-sub">{{ $a->note }}</div>@endif</td>
                    <td>{{ $a->user->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty">@include('partials.icon',['name'=>'clock'])<h4>{{ __('No adjustments recorded') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $adjustments->links('partials.pagination') }}</div>
</div>
@endsection
