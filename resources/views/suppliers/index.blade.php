@extends('layouts.app')
@section('title', 'Suppliers')
@section('crumb', 'Vendors and stock suppliers')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Suppliers') }}</h2><p>{{ $suppliers->total() }} {{ __('suppliers') }}</p></div>
    <div class="actions"><button class="btn btn-primary" onclick="SS.openModal('addSup')">@include('partials.icon',['name'=>'plus']) {{ __('Add Supplier') }}</button></div>
</div>

<form method="GET" class="toolbar">
    <div class="search-box">@include('partials.icon',['name'=>'search'])<input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search suppliers…') }}"></div>
    <button class="btn btn-light">{{ __('Search') }}</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Products') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($suppliers as $s)
                <tr>
                    <td><div class="t-title">{{ $s->name }}</div>@if($s->company)<div class="t-sub">{{ $s->company }}</div>@endif</td>
                    <td>@if($s->phone)<div>{{ $s->phone }}</div>@endif @if($s->email)<div class="t-sub">{{ $s->email }}</div>@endif</td>
                    <td><span class="badge badge-blue">{{ $s->products_count }}</span></td>
                    <td>@if($s->is_active)<span class="badge badge-green">{{ __('Active') }}</span>@else<span class="badge badge-gray">{{ __('Inactive') }}</span>@endif</td>
                    <td>
                        <div class="flex gap-1" style="justify-content:flex-end">
                            <button class="icon-btn" onclick='SS.edit("editSup",{{ Illuminate\Support\Js::from(['name'=>$s->name,'company'=>$s->company,'email'=>$s->email,'phone'=>$s->phone,'address'=>$s->address,'is_active'=>$s->is_active]) }}); document.getElementById("editSupForm").action="{{ route('suppliers.update',$s) }}"'>@include('partials.icon',['name'=>'edit'])</button>
                            <form method="POST" action="{{ route('suppliers.destroy',$s) }}" onsubmit="return SS.confirmSubmit(this,'{{ __('Delete this supplier?') }}')">@csrf @method('DELETE')<button class="icon-btn danger">@include('partials.icon',['name'=>'trash'])</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty">@include('partials.icon',['name'=>'truck'])<h4>{{ __('No suppliers yet') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $suppliers->links('partials.pagination') }}</div>
</div>

@foreach(['addSup'=>[__('Add Supplier'),route('suppliers.store'),false],'editSup'=>[__('Edit Supplier'),'#',true]] as $mid=>$cfg)
<div class="modal-overlay" id="{{ $mid }}" style="display:none">
    <form class="modal wide" method="POST" @if(!$cfg[2]) action="{{ $cfg[1] }}" @else id="editSupForm" @endif>
        @csrf @if($cfg[2]) @method('PUT') @endif
        <div class="modal-head"><h3>{{ $cfg[0] }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('{{ $mid }}')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="form-row"><div class="field"><label>{{ __('Name *') }}</label><input class="input" name="name" required></div>
            <div class="field"><label>{{ __('Company') }}</label><input class="input" name="company"></div></div>
            <div class="form-row"><div class="field"><label>{{ __('Phone') }}</label><input class="input" name="phone"></div>
            <div class="field"><label>{{ __('Email') }}</label><input class="input" type="email" name="email"></div></div>
            <div class="field" style="margin-bottom:14px"><label>{{ __('Address') }}</label><textarea class="input" name="address"></textarea></div>
            @if($cfg[2])<label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"><span class="track"></span><span style="font-size:13px;font-weight:600;color:var(--ink-soft)">{{ __('Active') }}</span></label>@endif
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('{{ $mid }}')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</div>
@endforeach
@endsection
