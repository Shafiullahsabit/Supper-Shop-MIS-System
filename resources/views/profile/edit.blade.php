@extends('layouts.app')
@section('title', 'My Profile')
@section('crumb', 'Manage your account')

@section('content')
@php
    $initials = collect(preg_split('/\s+/', trim($user->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->implode('');
@endphp

<div class="page-head">
    <div class="titles">
        <h2>{{ __('My Profile') }}</h2>
        <p>{{ __('Manage your personal information and account security.') }}</p>
    </div>
</div>

<section class="profile-hero">
    @include('partials.user-avatar', ['user' => $user, 'class' => 'profile-avatar', 'initials' => mb_strtoupper($initials)])
    <div class="profile-identity">
        <span class="profile-eyebrow">{{ __('Personal account') }}</span>
        <h2>{{ $user->name }}</h2>
        <div class="profile-identity-meta">
            <span class="badge badge-blue">{{ __($user->role_name) }}</span>
            <span class="profile-email">{{ $user->email }}</span>
        </div>
    </div>
    <div class="profile-version">
        <span class="dot"></span>
        <span>{{ __('System version') }}</span>
        <strong>v{{ config('app.version') }}</strong>
    </div>
</section>

<div class="profile-layout">
    <aside class="card profile-overview">
        <div class="profile-section-heading">
            <div class="profile-section-icon">@include('partials.icon', ['name' => 'user'])</div>
            <div>
                <h3>{{ __('Account overview') }}</h3>
                <p>{{ __('Your access and account details') }}</p>
            </div>
        </div>

        <dl class="profile-facts">
            <div>
                <dt>{{ __('Account status') }}</dt>
                <dd><span class="badge {{ $user->is_active ? 'badge-green' : 'badge-gray' }}"><span class="dot"></span>{{ $user->is_active ? __('Active') : __('Inactive') }}</span></dd>
            </div>
            <div>
                <dt>{{ __('Assigned branch') }}</dt>
                <dd>{{ $user->branch?->name ?? __('Not assigned') }}</dd>
            </div>
            <div>
                <dt>{{ __('Member since') }}</dt>
                <dd>{{ $user->created_at?->translatedFormat('d M Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt>{{ __('Last login') }}</dt>
                <dd>{{ $user->last_login_at?->translatedFormat('d M Y, h:i A') ?? __('This is your first session') }}</dd>
            </div>
        </dl>

        <div class="profile-security-note">
            @include('partials.icon', ['name' => 'shield'])
            <div>
                <strong>{{ __('Keep your account secure') }}</strong>
                <p>{{ __('Use a unique password and never share it with anyone.') }}</p>
            </div>
        </div>
    </aside>

    <div class="profile-forms">
        <section class="card profile-card">
            <div class="profile-section-heading">
                <div class="profile-section-icon">@include('partials.icon', ['name' => 'edit'])</div>
                <div>
                    <h3>{{ __('Account Details') }}</h3>
                    <p>{{ __('Update your name and contact information.') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="profile-form" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="form-row">
                    <div class="field">
                        <label for="profileName">{{ __('Name') }}</label>
                        <input class="input" id="profileName" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required maxlength="100">
                        @error('name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="profilePhone">{{ __('Phone') }}</label>
                        <input class="input" id="profilePhone" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" maxlength="40">
                        @error('phone')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="field">
                    <label for="profileAvatar">{{ __('Profile photo') }}</label>
                    <input class="input" id="profileAvatar" type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <p class="field-hint">{{ __('Choose a JPG, PNG, or WebP image up to 2 MB.') }}</p>
                    @error('avatar')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="profileEmail">{{ __('Email') }}</label>
                    <input class="input" id="profileEmail" type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email" required>
                    @error('email')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="profile-form-actions">
                    <button class="btn btn-primary">@include('partials.icon', ['name' => 'check']) {{ __('Save Changes') }}</button>
                </div>
            </form>
        </section>

        <section class="card profile-card">
            <div class="profile-section-heading">
                <div class="profile-section-icon profile-section-icon-secure">@include('partials.icon', ['name' => 'shield'])</div>
                <div>
                    <h3>{{ __('Change Password') }}</h3>
                    <p>{{ __('Choose a strong password that you do not use elsewhere.') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.password') }}" class="profile-form">
                @csrf
                @method('PUT')
                <div class="field">
                    <label for="currentPassword">{{ __('Current password') }}</label>
                    <div class="password-input">
                        <input class="input" id="currentPassword" type="password" name="current_password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-password-toggle="currentPassword" data-show-label="{{ __('Show') }}" data-hide-label="{{ __('Hide') }}" aria-label="{{ __('Show') }}">{{ __('Show') }}</button>
                    </div>
                    @error('current_password')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="newPassword">{{ __('New password') }}</label>
                        <div class="password-input">
                            <input class="input" id="newPassword" type="password" name="password" autocomplete="new-password" minlength="6" required data-password-strength-input>
                            <button type="button" class="password-toggle" data-password-toggle="newPassword" data-show-label="{{ __('Show') }}" data-hide-label="{{ __('Hide') }}" aria-label="{{ __('Show') }}">{{ __('Show') }}</button>
                        </div>
                        @error('password')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="confirmPassword">{{ __('Confirm new password') }}</label>
                        <div class="password-input">
                            <input class="input" id="confirmPassword" type="password" name="password_confirmation" autocomplete="new-password" minlength="6" required>
                            <button type="button" class="password-toggle" data-password-toggle="confirmPassword" data-show-label="{{ __('Show') }}" data-hide-label="{{ __('Hide') }}" aria-label="{{ __('Show') }}">{{ __('Show') }}</button>
                        </div>
                    </div>
                </div>
                <div class="password-strength" aria-live="polite">
                    <div class="password-strength-heading">
                        <span>{{ __('Password strength') }}</span>
                        <strong data-password-strength-label>{{ __('Not set') }}</strong>
                    </div>
                    <div class="password-strength-track" data-password-strength-meter data-level="0" data-labels="{{ __('Not set') }}|{{ __('Weak') }}|{{ __('Fair') }}|{{ __('Good') }}|{{ __('Strong') }}">
                        <span></span>
                    </div>
                    <p>{{ __('Your password must be at least :count characters.', ['count' => 6]) }}</p>
                </div>
                <div class="profile-form-actions">
                    <button class="btn btn-primary">@include('partials.icon', ['name' => 'shield']) {{ __('Update Password') }}</button>
                </div>
            </form>
        </section>
    </div>
</div>
@endsection
