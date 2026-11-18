@extends('layouts.app')
@section('title', 'My Profile')
@section('crumb', 'Manage your account')

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('My Profile') }}</h2><p>{{ $user->name }} · {{ $user->role_name }}</p></div>
</div>

<div class="grid grid-2" style="align-items:start">
    <div class="card card-pad">
        <h3 style="margin-bottom:16px">{{ __('Account Details') }}</h3>
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf @method('PUT')
            <div class="field"><label>{{ __('Name') }}</label><input class="input" name="name" value="{{ old('name',$user->name) }}" required></div>
            <div class="field"><label>{{ __('Email') }}</label><input class="input" type="email" name="email" value="{{ old('email',$user->email) }}" required></div>
            <div class="field"><label>{{ __('Phone') }}</label><input class="input" name="phone" value="{{ old('phone',$user->phone) }}"></div>
            <button class="btn btn-primary">@include('partials.icon',['name'=>'check']) {{ __('Save Changes') }}</button>
        </form>
    </div>

    <div class="card card-pad">
        <h3 style="margin-bottom:16px">{{ __('Change Password') }}</h3>
        <form method="POST" action="{{ route('profile.password') }}">
            @csrf @method('PUT')
            <div class="field"><label>{{ __('Current password') }}</label><input class="input" type="password" name="current_password" required></div>
            <div class="field"><label>{{ __('New password') }}</label><input class="input" type="password" name="password" required></div>
            <div class="field"><label>{{ __('Confirm new password') }}</label><input class="input" type="password" name="password_confirmation" required></div>
            <button class="btn btn-primary">@include('partials.icon',['name'=>'shield']) {{ __('Update Password') }}</button>
        </form>
        <div class="mt-3" style="padding-top:16px;border-top:1px solid var(--line-2)">
            <div class="text-muted fs-sm">{{ __('Last login') }}</div>
            <div class="fw-700">{{ $user->last_login_at?->translatedFormat('d M Y, h:i A') ?? __('This is your first session') }}</div>
        </div>
    </div>
</div>
@endsection
