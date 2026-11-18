@extends('layouts.app')
@section('title', 'Purchases')
@section('crumb', 'Stock receiving and purchase orders')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Purchases') }}</h2><p>{{ $purchases->total() }} {{ __('purchase records') }}</p></div>
    @can('purchases.manage')<div class="actions"><a href="{{ route('purchases.create') }}" class="btn btn-primary">@include('partials.icon',['name'=>'plus']) {{ __('New Purchase') }}</a></div>@endcan
</div>

<form method="GET" class="toolbar">
    <div class="search-box">@include('partials.icon',['name'=>'search'])<input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search reference…') }}"></div>
    <button class="btn btn-light">{{ __('Search') }}</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Date') }}</th>@if(auth()->user()->hasRole('Super Admin'))<th>{{ __('Branch') }}</th>@endif<th>{{ __('Supplier') }}</th><th>{{ __('Status') }}</th><th class="t-right">{{ __('Total') }}</th><th class="t-right">{{ __('Paid') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($purchases as $p)
                <tr onclick="location='{{ route('purchases.show',$p) }}'" style="cursor:pointer">
                    <td class="t-title t-mono">{{ $p->reference }}</td>
                    <td>{{ $p->purchase_date->translatedFormat('d M Y') }}</td>
                    @if(auth()->user()->hasRole('Super Admin'))<td>{{ $p->branch?->branch_number }} · {{ $p->branch?->name }}</td>@endif
                    <td>{{ $p->supplier->name ?? '—' }}</td>
                    <td>
                        @if($p->status=='received')<span class="badge badge-green">{{ __('Received') }}</span>
                        @elseif($p->status=='ordered')<span class="badge badge-blue">{{ __('Ordered') }}</span>
                        @else<span class="badge badge-amber">{{ __('Pending') }}</span>@endif
                    </td>
                    <td class="t-right t-mono fw-700">{{ money($p->total) }}</td>
                    <td class="t-right t-mono">{{ money($p->paid) }}</td>
                    <td onclick="event.stopPropagation()">
                        @can('purchases.manage')
                        <form method="POST" action="{{ route('purchases.destroy',$p) }}" onsubmit="return SS.confirmSubmit(this,'{{ __('Delete purchase and reverse stock?') }}')" style="text-align:right">@csrf @method('DELETE')<button class="icon-btn danger">@include('partials.icon',['name'=>'trash'])</button></form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ auth()->user()->hasRole('Super Admin') ? 8 : 7 }}"><div class="empty">@include('partials.icon',['name'=>'inbox'])<h4>{{ __('No purchases recorded') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $purchases->links('partials.pagination') }}</div>
</div>
@endsection
