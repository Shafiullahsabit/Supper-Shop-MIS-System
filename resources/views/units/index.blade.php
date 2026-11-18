@extends('layouts.app')
@section('title', 'Units')
@section('crumb', 'Units of measurement')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Units') }}</h2><p>{{ __('Measurement units used across products') }}</p></div>
    <div class="actions"><button class="btn btn-primary" onclick="SS.openModal('addUnit')">@include('partials.icon',['name'=>'plus']) {{ __('Add Unit') }}</button></div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Unit') }}</th><th>{{ __('Short') }}</th><th>{{ __('Decimals') }}</th><th>{{ __('Products') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($units as $u)
                <tr>
                    <td class="t-title">{{ $u->name }}</td>
                    <td><span class="badge badge-blue t-mono">{{ $u->short_name }}</span></td>
                    <td>@if($u->allow_decimal)<span class="badge badge-green">{{ __('Allowed') }}</span>@else<span class="badge badge-gray">{{ __('Whole only') }}</span>@endif</td>
                    <td>{{ $u->products_count }}</td>
                    <td>
                        <div class="flex gap-1" style="justify-content:flex-end">
                            <button class="icon-btn" onclick='SS.edit("editUnit",{{ Illuminate\Support\Js::from(['name'=>$u->name,'short_name'=>$u->short_name,'allow_decimal'=>$u->allow_decimal]) }}); document.getElementById("editUnitForm").action="{{ route('units.update',$u) }}"'>@include('partials.icon',['name'=>'edit'])</button>
                            <form method="POST" action="{{ route('units.destroy',$u) }}" onsubmit="return SS.confirmSubmit(this,'{{ __('Delete this unit?') }}')">@csrf @method('DELETE')<button class="icon-btn danger">@include('partials.icon',['name'=>'trash'])</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty">@include('partials.icon',['name'=>'ruler'])<h4>{{ __('No units defined') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addUnit" style="display:none">
    <form class="modal" method="POST" action="{{ route('units.store') }}">
        @csrf
        <div class="modal-head"><h3>{{ __('Add Unit') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('addUnit')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="form-row"><div class="field"><label>{{ __('Name') }}</label><input class="input" name="name" placeholder="{{ __('Kilogram') }}" required></div>
            <div class="field"><label>{{ __('Short name') }}</label><input class="input" name="short_name" placeholder="{{ __('kg') }}" required></div></div>
            <label class="switch"><input type="hidden" name="allow_decimal" value="0"><input type="checkbox" name="allow_decimal" value="1"><span class="track"></span><span style="font-size:13px;font-weight:600;color:var(--ink-soft)">{{ __('Allow decimal quantities (e.g. 1.5 kg)') }}</span></label>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('addUnit')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</div>

<div class="modal-overlay" id="editUnit" style="display:none">
    <form class="modal" method="POST" id="editUnitForm">
        @csrf @method('PUT')
        <div class="modal-head"><h3>{{ __('Edit Unit') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('editUnit')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="form-row"><div class="field"><label>{{ __('Name') }}</label><input class="input" name="name" required></div>
            <div class="field"><label>{{ __('Short name') }}</label><input class="input" name="short_name" required></div></div>
            <label class="switch"><input type="hidden" name="allow_decimal" value="0"><input type="checkbox" name="allow_decimal" value="1"><span class="track"></span><span style="font-size:13px;font-weight:600;color:var(--ink-soft)">{{ __('Allow decimal quantities') }}</span></label>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('editUnit')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Update') }}</button></div>
    </form>
</div>
@endsection
