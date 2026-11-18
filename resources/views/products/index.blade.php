@extends('layouts.app')
@section('title', 'Products')
@section('crumb', 'Manage your product catalog')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Products') }}</h2><p>{{ $products->total() }} {{ __('items in your catalog') }}</p></div>
    <div class="actions">
        @can('products.create')
            <a href="{{ route('products.create') }}" class="btn btn-primary">@include('partials.icon',['name'=>'plus']) {{ __('Add Product') }}</a>
        @endcan
    </div>
</div>

<form method="GET" class="toolbar">
    <div class="search-box">
        @include('partials.icon',['name'=>'search'])
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search name, SKU or barcode…') }}">
    </div>
    <select name="category" class="select" style="width:auto" onchange="this.form.submit()">
        <option value="">{{ __('All categories') }}</option>
        @foreach($categories as $c)
            <option value="{{ $c->id }}" @selected(request('category')==$c->id)>{{ $c->name }}</option>
        @endforeach
    </select>
    <select name="stock" class="select" style="width:auto" onchange="this.form.submit()">
        <option value="">{{ __('Any stock') }}</option>
        <option value="low" @selected(request('stock')=='low')>{{ __('Low stock') }}</option>
        <option value="out" @selected(request('stock')=='out')>{{ __('Out of stock') }}</option>
    </select>
    <button class="btn btn-light">{{ __('Filter') }}</button>
    @if(request()->hasAny(['q','category','stock']))<a href="{{ route('products.index') }}" class="btn btn-light">{{ __('Reset') }}</a>@endif
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Product') }}</th><th>{{ __('Category') }}</th><th>{{ __('SKU / Barcode') }}</th>
                    <th class="t-right">{{ __('Cost') }}</th><th class="t-right">{{ __('Price') }}</th>
                    <th class="t-right">{{ __('Stock') }}</th><th>{{ __('Status') }}</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($products as $p)
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="p-top" style="width:34px;height:34px;border-radius:9px;display:grid;place-items:center;color:#fff;font-weight:800;background:{{ $p->category->color ?? '#2563eb' }}">{{ strtoupper(substr($p->name,0,1)) }}</div>
                            <div><div class="t-title">{{ $p->name }}</div><div class="t-sub">{{ $p->unit->short_name ?? '' }} · {{ $p->profit_margin }}% margin</div></div>
                        </div>
                    </td>
                    <td>@if($p->category)<span class="badge badge-blue">{{ $p->category->name }}</span>@else <span class="text-muted">—</span>@endif</td>
                    <td><div class="t-mono">{{ $p->sku }}</div><div class="t-sub t-mono">{{ $p->barcode ?? __('no barcode') }}</div></td>
                    <td class="t-right t-mono">{{ money($p->cost_price) }}</td>
                    <td class="t-right t-mono fw-700">{{ money($p->sale_price) }}</td>
                    <td class="t-right t-mono">{{ rtrim(rtrim(number_format($p->stock_quantity,3),'0'),'.') }}</td>
                    <td>
                        @if($p->stock_status=='out')<span class="badge badge-red"><span class="dot"></span> {{ __('Out') }}</span>
                        @elseif($p->stock_status=='low')<span class="badge badge-amber"><span class="dot"></span> {{ __('Low') }}</span>
                        @else<span class="badge badge-green"><span class="dot"></span> {{ __('In stock') }}</span>@endif
                    </td>
                    <td>
                        <div class="flex gap-1 justify-between" style="justify-content:flex-end">
                            @can('products.edit')<a href="{{ route('products.edit',$p) }}" class="icon-btn" title="{{ __('Edit') }}">@include('partials.icon',['name'=>'edit'])</a>@endcan
                            @can('products.delete')
                                <form method="POST" action="{{ route('products.destroy',$p) }}" onsubmit="return SS.confirmSubmit(this,'Delete {{ addslashes($p->name) }}?')">
                                    @csrf @method('DELETE')
                                    <button class="icon-btn danger" title="{{ __('Delete') }}">@include('partials.icon',['name'=>'trash'])</button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty">@include('partials.icon',['name'=>'box'])<h4>{{ __('No products found') }}</h4><p>{{ __('Add your first product to get started.') }}</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $products->links('partials.pagination') }}</div>
</div>
@endsection
