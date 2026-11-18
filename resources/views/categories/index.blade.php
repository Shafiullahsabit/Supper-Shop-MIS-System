@extends('layouts.app')
@section('title', 'Categories')
@section('crumb', 'Organize products into categories')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Categories') }}</h2><p>{{ $categories->total() }} {{ __('categories') }}</p></div>
    <div class="actions"><button class="btn btn-primary" onclick="SS.openModal('addCat')">@include('partials.icon',['name'=>'plus']) {{ __('Add Category') }}</button></div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('Category') }}</th><th>{{ __('Products') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($categories as $c)
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            <span style="width:16px;height:16px;border-radius:5px;background:{{ $c->color }}"></span>
                            <div><div class="t-title">{{ $c->name }}</div>@if($c->description)<div class="t-sub">{{ Str::limit($c->description,50) }}</div>@endif</div>
                        </div>
                    </td>
                    <td><span class="badge badge-blue">{{ $c->products_count }}</span></td>
                    <td>@if($c->is_active)<span class="badge badge-green">{{ __('Active') }}</span>@else<span class="badge badge-gray">{{ __('Hidden') }}</span>@endif</td>
                    <td>
                        <div class="flex gap-1" style="justify-content:flex-end">
                            <button class="icon-btn" title="{{ __('Edit') }}"
                                onclick='SS.edit("editCat",{{ Illuminate\Support\Js::from(['name'=>$c->name,'color'=>$c->color,'description'=>$c->description,'is_active'=>$c->is_active]) }}); document.getElementById("editCatForm").action="{{ route('categories.update',$c) }}"'>
                                @include('partials.icon',['name'=>'edit'])
                            </button>
                            <form method="POST" action="{{ route('categories.destroy',$c) }}" onsubmit="return SS.confirmSubmit(this,'{{ __('Delete this category?') }}')">
                                @csrf @method('DELETE')
                                <button class="icon-btn danger">@include('partials.icon',['name'=>'trash'])</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty">@include('partials.icon',['name'=>'tag'])<h4>{{ __('No categories yet') }}</h4></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $categories->links('partials.pagination') }}</div>
</div>

{{-- Add modal --}}
<div class="modal-overlay" id="addCat" style="display:none">
    <form class="modal" method="POST" action="{{ route('categories.store') }}">
        @csrf
        <div class="modal-head"><h3>{{ __('Add Category') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('addCat')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="field"><label>{{ __('Name') }}</label><input class="input" name="name" required></div>
            <div class="field"><label>{{ __('Color') }}</label><input class="input" type="color" name="color" value="#2563eb" style="height:44px;padding:4px"></div>
            <div class="field" style="margin:0"><label>{{ __('Description') }}</label><textarea class="input" name="description"></textarea></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('addCat')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</div>

{{-- Edit modal --}}
<div class="modal-overlay" id="editCat" style="display:none">
    <form class="modal" method="POST" id="editCatForm">
        @csrf @method('PUT')
        <div class="modal-head"><h3>{{ __('Edit Category') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('editCat')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="field"><label>{{ __('Name') }}</label><input class="input" name="name" required></div>
            <div class="field"><label>{{ __('Color') }}</label><input class="input" type="color" name="color" style="height:44px;padding:4px"></div>
            <div class="field"><label>{{ __('Description') }}</label><textarea class="input" name="description"></textarea></div>
            <label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"><span class="track"></span><span style="font-size:13px;font-weight:600;color:var(--ink-soft)">{{ __('Active') }}</span></label>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('editCat')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Update') }}</button></div>
    </form>
</div>
@endsection
