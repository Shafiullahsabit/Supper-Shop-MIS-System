@extends('layouts.app')
@section('title', 'Users')
@section('crumb', 'Staff accounts and roles')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Users') }}</h2><p>{{ $users->total() }} {{ __('staff accounts') }}</p></div>
    <div class="actions"><button class="btn btn-primary" onclick="SS.openModal('addUser')">@include('partials.icon',['name'=>'plus']) {{ __('Add User') }}</button></div>
</div>

<form method="GET" class="toolbar">
    <div class="search-box">@include('partials.icon',['name'=>'search'])<input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search name or email…') }}"></div>
    <button class="btn btn-light">{{ __('Search') }}</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>{{ __('User') }}</th>@if($isSuperAdmin)<th>{{ __('Branch') }}</th>@endif<th>{{ __('Role') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Last Login') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($users as $usr)
                @php
                    $editData = [
                        'name' => $usr->name,
                        'email' => $usr->email,
                        'phone' => $usr->phone,
                        'role' => $usr->roles->pluck('name')->first(),
                        'is_active' => $usr->is_active,
                    ];
                    if ($isSuperAdmin) {
                        $editData['branch_id'] = $usr->branch_id;
                    }
                @endphp
                <tr>
                    <td><div class="flex items-center gap-2">@include('partials.user-avatar', ['user' => $usr, 'style' => 'width:36px;height:36px;font-size:14px;flex:none'])<div><div class="t-title">{{ $usr->name }}</div><div class="t-sub">{{ $usr->email }}</div></div></div></td>
                    @if($isSuperAdmin)<td>{{ $usr->branch?->branch_number }} · {{ $usr->branch?->name }}</td>@endif
                    <td>@foreach($usr->roles as $r)<span class="badge badge-violet">{{ $r->name }}</span>@endforeach</td>
                    <td>{{ $usr->phone ?? '—' }}</td>
                    <td>{{ $usr->last_login_at?->diffForHumans() ?? __('Never') }}</td>
                    <td>@if($usr->is_active)<span class="badge badge-green"><span class="dot"></span> {{ __('Active') }}</span>@else<span class="badge badge-red"><span class="dot"></span> {{ __('Disabled') }}</span>@endif</td>
                    <td>
                        <div class="flex gap-1" style="justify-content:flex-end">
                            <button
                                type="button"
                                class="icon-btn"
                                data-edit-user
                                data-user="{{ Illuminate\Support\Js::encode($editData) }}"
                                data-action="{{ route('users.update', $usr) }}"
                                aria-label="{{ __('Edit User') }}"
                                title="{{ __('Edit User') }}"
                            >@include('partials.icon',['name'=>'edit'])</button>
                            @if($usr->id !== auth()->id())
                            <form
                                method="POST"
                                action="{{ route('users.destroy', $usr) }}"
                                data-user-action-confirm
                                data-confirm-title="{{ __('Confirm user deletion') }}"
                                data-confirm-message="{{ __('Are you sure you want to delete this user? This action cannot be undone.') }}"
                                data-confirm-label="{{ __('Delete') }}"
                                data-confirm-variant="danger"
                            >@csrf @method('DELETE')<button type="submit" class="icon-btn danger" aria-label="{{ __('Delete User') }}" title="{{ __('Delete User') }}">@include('partials.icon',['name'=>'trash'])</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div style="padding:0 16px 12px">{{ $users->links('partials.pagination') }}</div>
</div>

{{-- Add --}}
<div class="modal-overlay" id="addUser" style="display:none">
    <form class="modal" method="POST" action="{{ route('users.store') }}">
        @csrf
        <div class="modal-head"><h3>{{ __('Add User') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('addUser')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="form-row"><div class="field"><label>{{ __('Name *') }}</label><input class="input" name="name" required></div>
            <div class="field"><label>{{ __('Phone') }}</label><input class="input" name="phone"></div></div>
            <div class="field"><label>{{ __('Email *') }}</label><input class="input" type="email" name="email" required></div>
            <div class="form-row"><div class="field"><label>{{ __('Password *') }}</label><input class="input" type="password" name="password" required></div>
            <div class="field"><label>{{ __('Role *') }}</label><select class="select" name="role" required>@foreach($roles as $r)<option value="{{ $r->name }}">{{ $r->name }}</option>@endforeach</select></div></div>
            @if($isSuperAdmin)
                <div class="field"><label>{{ __('Branch *') }}</label><select class="select" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->branch_number }} · {{ $branch->name }}</option>@endforeach</select></div>
            @endif
            <label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked><span class="track"></span><span style="font-size:13px;font-weight:600;color:var(--ink-soft)">{{ __('Active') }}</span></label>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('addUser')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Create User') }}</button></div>
    </form>
</div>

{{-- Edit --}}
<div class="modal-overlay" id="editUser" style="display:none">
    <form
        class="modal"
        method="POST"
        id="editUserForm"
        data-user-action-confirm
        data-confirm-title="{{ __('Confirm user changes') }}"
        data-confirm-message="{{ __('Are you sure you want to save changes to this user?') }}"
        data-confirm-label="{{ __('Save changes') }}"
        data-confirm-variant="primary"
    >
        @csrf @method('PUT')
        <div class="modal-head"><h3>{{ __('Edit User') }}</h3><button type="button" class="icon-btn" style="margin-inline-start:auto" onclick="SS.closeModal('editUser')">@include('partials.icon',['name'=>'x'])</button></div>
        <div class="modal-body">
            <div class="form-row"><div class="field"><label>{{ __('Name *') }}</label><input class="input" name="name" required></div>
            <div class="field"><label>{{ __('Phone') }}</label><input class="input" name="phone"></div></div>
            <div class="field"><label>{{ __('Email *') }}</label><input class="input" type="email" name="email" required></div>
            <div class="form-row"><div class="field"><label>{{ __('New password') }}</label><input class="input" type="password" name="password" placeholder="{{ __('Leave blank to keep') }}"></div>
            <div class="field"><label>{{ __('Role *') }}</label><select class="select" name="role" required>@foreach($roles as $r)<option value="{{ $r->name }}">{{ $r->name }}</option>@endforeach</select></div></div>
            @if($isSuperAdmin)
                <div class="field"><label>{{ __('Branch *') }}</label><select class="select" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->branch_number }} · {{ $branch->name }}</option>@endforeach</select></div>
            @endif
            <label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"><span class="track"></span><span style="font-size:13px;font-weight:600;color:var(--ink-soft)">{{ __('Active') }}</span></label>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-light" onclick="SS.closeModal('editUser')">{{ __('Cancel') }}</button><button class="btn btn-primary">{{ __('Update User') }}</button></div>
    </form>
</div>

<div class="modal-overlay" id="userActionConfirmation" style="display:none">
    <section class="modal user-action-confirmation" role="alertdialog" aria-modal="true" aria-labelledby="userActionConfirmationTitle" aria-describedby="userActionConfirmationMessage">
        <div class="user-action-confirmation-icon">@include('partials.icon', ['name' => 'alert'])</div>
        <h2 id="userActionConfirmationTitle">{{ __('Confirm') }}</h2>
        <p id="userActionConfirmationMessage">{{ __('Are you sure?') }}</p>
        <div class="user-action-confirmation-actions">
            <button type="button" class="btn btn-light" data-cancel-user-action>{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" data-confirm-user-action>{{ __('Confirm') }}</button>
        </div>
    </section>
</div>
@endsection
