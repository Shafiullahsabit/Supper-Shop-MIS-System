@extends('layouts.app')
@section('title', 'Customers')
@section('crumb', 'Customer directory and loyalty')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Customers') }}</h2><p>{{ $customers->total() }} {{ __('customers') }}</p></div>
    @can('customers.manage')<div class="actions"><button class="btn btn-primary" onclick="SS.openModal('addCust')">@include('partials.icon',['name'=>'plus']) {{ __('Add Customer') }}</button></div>@endcan
</div>

<form method="GET" class="toolbar">
    <div class="search-box">@include('partials.icon',['name'=>'search'])<input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search name, phone or email…') }}"></div>
    <button class="btn btn-light">{{ __('Search') }}</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Contact') }}</th><th class="t-right">{{ __('Orders') }}</th><th class="t-right">{{ __('Total Spent') }}</th><th class="t-right">{{ __('Points') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($customers as $c)
                <tr onclick="location='{{ route('customers.show',$c) }}'" style="cursor:pointer">
                    <td><div class="flex items-center gap-2"><div class="avatar" style="width:34px;height:34px;font-size:13px">{{ strtoupper(substr($c->name,0,1)) }}</div><div class="t-title">{{ $c->name }}</div></div></td>
                    <td>@if($c->phone)<div>{{ $c->phone }}</div>@endif @if($c->email)<div class="t-sub">{{ $c->email }}</div>@endif</td>
                    <td class="t-right">{{ $c->sales_count }}</td>
                    <td class="t-right t-mono fw-700">{{ money($c->total_spent) }}</td>
                    <td class="t-right"><span class="badge badge-violet">@include('partials.icon',['name'=>'star']) {{ $c->loyalty_points }}</span></td>
                    <td onclick="event.stopPropagation()">
                        @can('customers.manage')
                        <div class="flex gap-1" style="justify-content:flex-end">
                            <button class="icon-btn" onclick='SS.edit("editCust",{{ Illuminate\Support\Js::from(['name'=>$c->name,'phone'=>$c->phone,'email'=>$c->email,'address'=>$c->address,'loyalty_points'=>$c->loyalty_points,'is_active'=>$c->is_active]) }}); document.getElementById("editCustForm").action="{{ route('customers.update',$c) }}"'>@include('partials.icon',['name'=>'edit'])</button>
                            <form method="POST" action="{{ route('customers.destroy',$c) }}" onsubmit="return SS.confirmSubmit(this,'{{ __('Delete this customer?') }}')">@csrf @method('DELETE')<button class="icon-btn danger">@include('partials.icon',['name'=>'trash'])</button></form>
                        </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty">@include('partials.icon',['name'=>'users'])<h4>{{ __('No customers yet') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $customers->links('partials.pagination') }}</div>
</div>

@can('customers.manage')
@foreach(['addCust'=>[__('Add Customer'),route('customers.store'),false],'editCust'=>[__('Edit Customer'),'#',true]] as $mid=>$cfg)
<div class="modal-overlay" id="{{ $mid }}" style="display:none">
    <form class="modal" method="POST" @if(!$cfg[2]) action="{{ $cfg[1] }}" @else id="editCustForm" @endif>
        @csrf @if($cfg[2]) @method('PUT') @endif
        <div class="modal-head"><h3>{{ $cfg[0] }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('{{ $mid }}')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="field"><label>{{ __('Name *') }}</label><input class="input" name="name" required></div>
            <div class="form-row"><div class="field"><label>{{ __('Phone') }}</label><input class="input" name="phone"></div>
            <div class="field"><label>{{ __('Email') }}</label><input class="input" type="email" name="email"></div></div>
            <div class="field"><label>{{ __('Address') }}</label><textarea class="input" name="address"></textarea></div>
            @if($cfg[2])<div class="field" style="margin:0"><label>{{ __('Loyalty points') }}</label><input class="input t-mono" type="number" name="loyalty_points" min="0"></div>@endif
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('{{ $mid }}')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</div>
@endforeach
@endcan
@endsection
