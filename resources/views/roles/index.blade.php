@extends('layouts.app')
@section('title', 'Roles & Access')
@section('crumb', 'Role-based permission control')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Roles & Access') }}</h2><p>{{ __('Control what each role can do') }}</p></div>
    <div class="actions"><button class="btn btn-primary" onclick="SS.openModal('addRole')">@include('partials.icon',['name'=>'plus']) {{ __('New Role') }}</button></div>
</div>

<div style="display:flex;flex-direction:column;gap:18px">
@foreach($roles as $role)
    <div class="card">
        <form method="POST" action="{{ route('roles.update',$role) }}">
            @csrf @method('PUT')
            <div class="card-head">
                <div class="ico ico-violet" style="position:static;width:40px;height:40px">@include('partials.icon',['name'=>'shield'])</div>
                <div><h3>{{ $role->name }}</h3><div class="sub">{{ $role->users_count }} {{ __($role->users_count === 1 ? 'user' : 'users') }} · {{ $role->permissions->count() }} {{ __('permissions') }}</div></div>
                <div style="margin-inline-start:auto" class="flex gap-2">
                    @if(!in_array($role->name,['Admin','Manager','Cashier','Super Admin']))
                        <button type="button" class="icon-btn danger" data-confirm="{{ __('Delete role :name?', ['name' => $role->name]) }}" onclick="return SS.confirmSubmit(document.getElementById('delRole{{ $role->id }}'), this.dataset.confirm)">@include('partials.icon',['name'=>'trash'])</button>
                    @endif
                    @if($role->name !== 'Super Admin')
                        <button class="btn btn-primary btn-sm">@include('partials.icon',['name'=>'check']) {{ __('Save') }}</button>
                    @endif
                </div>
            </div>
            <div class="card-pad">
                @if($role->name==='Super Admin')
                    <div class="alert alert-info" style="margin:0">@include('partials.icon',['name'=>'shield']) {{ __('Super Admin has global access across all branches and cannot be changed here.') }}</div>
                @elseif($role->name==='Admin')
                    <div class="alert alert-info" style="margin:0">@include('partials.icon',['name'=>'shield']) {{ __('Admin has full module access within the assigned branch.') }}</div>
                @else
                <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px">
                    @foreach($permissions as $group => $perms)
                        <div style="border:1px solid var(--line-2);border-radius:11px;padding:12px 14px">
                            <div class="fw-700" style="text-transform:capitalize;margin-bottom:8px;font-size:13px;color:var(--blue-700)">{{ __(ucfirst(str_replace('_',' ',$group))) }}</div>
                            @foreach($perms as $perm)
                                <label class="flex items-center gap-2" style="padding:4px 0;cursor:pointer;font-size:12.5px">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->name }}" @checked($role->permissions->contains('name',$perm->name)) style="width:16px;height:16px;accent-color:var(--blue-600)">
                                    @php [$permissionGroup, $permissionAction] = explode('.', $perm->name, 2); @endphp
                                    <span>{{ __(ucfirst(str_replace('_', ' ', $permissionGroup))) }} · {{ __(ucfirst(str_replace('_', ' ', $permissionAction))) }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                @endif
            </div>
        </form>
        @if(!in_array($role->name,['Admin','Manager','Cashier','Super Admin']))
        <form method="POST" action="{{ route('roles.destroy',$role) }}" id="delRole{{ $role->id }}">@csrf @method('DELETE')</form>
        @endif
    </div>
@endforeach
</div>

<div class="modal-overlay" id="addRole" style="display:none">
    <form class="modal" method="POST" action="{{ route('roles.store') }}">
        @csrf
        <div class="modal-head"><h3>{{ __('New Role') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('addRole')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="field" style="margin:0"><label>{{ __('Role name') }}</label><input class="input" name="name" placeholder="{{ __('e.g. Supervisor') }}" required>
                <div class="hint">{{ __('You can assign permissions after creating the role.') }}</div></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('addRole')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Create Role') }}</button></div>
    </form>
</div>
@endsection
