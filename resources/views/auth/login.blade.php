<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} · {{ setting('shop_name', 'SuperShop') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body { background: var(--bg); }
        .login-wrap { min-height: 100vh; display: grid; grid-template-columns: 1.1fr 1fr; }
        .login-hero {
            background: linear-gradient(160deg, var(--blue-800), var(--blue-600) 60%, #1f5fd6);
            color: #fff; padding: 56px; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden;
        }
        .login-hero::after { content:""; position:absolute; width:460px; height:460px; border-radius:50%; background:rgba(255,255,255,.07); bottom:-160px; inset-inline-end:-120px; }
        .login-hero::before { content:""; position:absolute; width:280px; height:280px; border-radius:50%; background:rgba(255,255,255,.06); top:-90px; inset-inline-start:-60px; }
        .lh-brand { display:flex; align-items:center; gap:14px; position:relative; z-index:1; }
        .lh-brand .brand-mark { width:52px; height:52px; font-size:26px; }
        .lh-brand .n { font-size:24px; font-weight:800; }
        .lh-main { position:relative; z-index:1; }
        .lh-main h1 { color:#fff; font-size:38px; line-height:1.15; letter-spacing:-.02em; margin-bottom:18px; }
        .lh-main p { color:#d6e3ff; font-size:16px; max-width:400px; }
        .lh-feats { display:flex; flex-direction:column; gap:14px; margin-top:26px; }
        .lh-feat { display:flex; align-items:center; gap:12px; color:#eaf1ff; font-weight:600; }
        .lh-feat svg { width:20px; height:20px; }
        .lh-feat .fi { width:36px; height:36px; border-radius:10px; background:rgba(255,255,255,.14); display:grid; place-items:center; }
        .login-form-side { display:flex; align-items:center; justify-content:center; padding:40px; }
        .login-card { width:100%; max-width:400px; }
        .login-language { display:flex; justify-content:flex-end; margin-bottom:12px; }
        .login-card h2 { font-size:26px; margin-bottom:6px; }
        .login-card .sub { color:var(--muted); margin-bottom:28px; }
        .demo-creds { margin-top:22px; padding:14px 16px; background:var(--blue-50); border:1px solid var(--blue-100); border-radius:12px; font-size:12.5px; color:var(--ink-soft); }
        .demo-creds b { color:var(--blue-700); }
        .demo-creds .row { display:flex; justify-content:space-between; padding:3px 0; }
        .demo-creds code { background:#fff; padding:1px 7px; border-radius:6px; border:1px solid var(--line); font-size:12px; cursor:pointer; }
        @media (max-width: 900px){ .login-wrap{grid-template-columns:1fr} .login-hero{display:none} }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-hero">
        <div class="lh-brand">
            <div class="brand-mark">S</div>
            <div class="n">{{ setting('shop_name', 'SuperShop') }}</div>
        </div>
        <div class="lh-main">
            <h1>{!! __('Run your store,<br>not your paperwork.') !!}</h1>
            <p>{{ __('A complete point-of-sale and inventory platform built for busy supermarkets and retail counters.') }}</p>
            <div class="lh-feats">
                <div class="lh-feat"><span class="fi">@include('partials.icon',['name'=>'pos'])</span> {{ __('Fast barcode checkout & cash drawer') }}</div>
                <div class="lh-feat"><span class="fi">@include('partials.icon',['name'=>'boxes'])</span> {{ __('Live inventory & low-stock alerts') }}</div>
                <div class="lh-feat"><span class="fi">@include('partials.icon',['name'=>'shield'])</span> {{ __('Role-based access & secure backups') }}</div>
            </div>
        </div>
        <div style="color:#aec7f5;font-size:13px;position:relative;z-index:1">© {{ date('Y') }} {{ setting('shop_name','SuperShop') }}. {{ __('All rights reserved.') }}</div>
    </div>

    <div class="login-form-side">
        <div class="login-card">
            <div class="login-language">
                <div class="lang-switch">
                    <a href="{{ route('locale.switch','en') }}" class="{{ app()->getLocale() === 'en' ? 'on' : '' }}">English</a>
                    <a href="{{ route('locale.switch','prs') }}" class="{{ app()->getLocale() === 'prs' ? 'on' : '' }}">دری</a>
                    <a href="{{ route('locale.switch','ps') }}" class="{{ app()->getLocale() === 'ps' ? 'on' : '' }}">پښتو</a>
                </div>
            </div>
            <h2>{{ __('Welcome back') }}</h2>
            <p class="sub">{{ __('Sign in to your account to continue.') }}</p>

            @if($errors->any())
                <div class="alert alert-error">@include('partials.icon',['name'=>'alert']) {{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="field">
                    <label>{{ __('Email address') }}</label>
                    <input type="email" name="email" id="email" class="input input-lg" value="{{ old('email') }}" placeholder="you@store.com" required autofocus>
                </div>
                <div class="field">
                    <label>{{ __('Password') }}</label>
                    <input type="password" name="password" id="password" class="input input-lg" placeholder="••••••••" required>
                </div>
                <label class="switch" style="margin-bottom:20px">
                    <input type="checkbox" name="remember">
                    <span class="track"></span>
                    <span style="font-size:13px;font-weight:600;color:var(--ink-soft)">{{ __('Keep me signed in') }}</span>
                </label>
                <button type="submit" class="btn btn-primary btn-block btn-lg" style="padding:13px;font-size:15px">
                    {{ __('Sign in') }} @include('partials.icon',['name'=>'arrow-left','class'=>''])
                </button>
            </form>

            <div class="demo-creds">
                <div style="margin-bottom:6px"><b>{{ __('Demo accounts') }}</b> · {{ __('password') }}: <code onclick="fill(this)">password</code></div>
                <div class="row"><span>Admin</span> <code onclick="fillEmail('admin@supershop.test')">admin@supershop.test</code></div>
                <div class="row"><span>Manager</span> <code onclick="fillEmail('manager@supershop.test')">manager@supershop.test</code></div>
                <div class="row"><span>Cashier</span> <code onclick="fillEmail('cashier@supershop.test')">cashier@supershop.test</code></div>
            </div>
        </div>
    </div>
</div>
<script>
    function fillEmail(e){ document.getElementById('email').value=e; document.getElementById('password').value='password'; document.getElementById('password').focus(); }
    function fill(){ document.getElementById('password').value='password'; }
</script>
</body>
</html>
