<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} · {{ setting('shop_name', 'SuperShop') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;650;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body { min-height: 100vh; background: #eef3fb; }
        .login-wrap { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(0, .92fr); }
        .login-hero {
            isolation: isolate; color: #fff; padding: clamp(36px, 5vw, 76px);
            display: flex; flex-direction: column; justify-content: space-between; gap: 28px;
            position: relative; overflow: hidden;
            background:
                radial-gradient(ellipse at 18% 82%, rgba(86, 145, 255, .36), transparent 34%),
                radial-gradient(ellipse at 90% 8%, rgba(89, 190, 255, .22), transparent 30%),
                linear-gradient(145deg, #0b1b38 0%, #123579 54%, #2258c9 100%);
        }
        .login-hero::before, .login-hero::after {
            content: ""; position: absolute; z-index: -1; border: 1px solid rgba(255,255,255,.12);
            border-radius: 50%; pointer-events: none;
        }
        .login-hero::before { width: 520px; height: 520px; inset-block-end: -300px; inset-inline-end: -190px; box-shadow: 0 0 0 36px rgba(255,255,255,.025), 0 0 0 76px rgba(255,255,255,.02); }
        .login-hero::after { width: 320px; height: 320px; inset-block-start: -210px; inset-inline-start: -150px; }
        .lh-brand { display:flex; align-items:center; gap:14px; position:relative; z-index:1; }
        .lh-brand .brand-mark { width:52px; height:52px; font-size:26px; border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,.24); }
        .lh-brand .n { font-size:24px; font-weight:800; letter-spacing:-.03em; }
        .lh-main { position:relative; z-index:1; width:min(100%, 680px); margin-block:auto; }
        .lh-eyebrow { display:inline-flex; align-items:center; gap:9px; color:#c6d9ff; font-size:12px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; margin-bottom:18px; }
        .lh-eyebrow span { width:8px; height:8px; border-radius:50%; background:#4adea4; box-shadow:0 0 0 5px rgba(74,222,164,.14); }
        .lh-main h1 { color:#fff; font-size:clamp(36px, 4vw, 54px); line-height:1.12; letter-spacing:-.035em; margin-bottom:18px; max-width:650px; }
        .lh-main p { color:#d6e3ff; font-size:16px; line-height:1.8; max-width:510px; margin:0; }
        .lh-feats { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; margin-top:30px; }
        .lh-feat { min-height:94px; display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between; gap:12px; padding:14px; color:#f1f6ff; font-size:12px; line-height:1.5; font-weight:650; border:1px solid rgba(255,255,255,.14); border-radius:15px; background:rgba(255,255,255,.075); backdrop-filter:blur(12px); }
        .lh-feat svg { width:19px; height:19px; }
        .lh-feat .fi { width:34px; height:34px; border-radius:11px; background:rgba(255,255,255,.13); display:grid; place-items:center; color:#bcd3ff; }
        .lh-visual { position:relative; display:grid; grid-template-columns:58px 1fr; gap:12px; width:min(100%, 490px); height:150px; margin-top:30px; padding:12px; border:1px solid rgba(255,255,255,.2); border-radius:18px; background:linear-gradient(135deg, rgba(255,255,255,.15), rgba(255,255,255,.06)); box-shadow:0 24px 60px rgba(1,13,39,.2); transform:perspective(900px) rotateY(-5deg); }
        html[dir="rtl"] .lh-visual { transform:perspective(900px) rotateY(5deg); }
        .visual-rail, .visual-panel { border:1px solid rgba(255,255,255,.1); border-radius:12px; background:rgba(9,24,55,.34); }
        .visual-rail { display:flex; flex-direction:column; align-items:center; gap:10px; padding:10px 0; }
        .visual-rail span { width:22px; height:22px; border-radius:7px; background:rgba(255,255,255,.16); }
        .visual-rail span:first-child { background:#91b7ff; }
        .visual-panel { padding:12px; }
        .visual-lines { display:flex; align-items:center; gap:7px; margin-bottom:12px; }
        .visual-lines span { height:6px; border-radius:10px; background:rgba(255,255,255,.24); }
        .visual-lines span:first-child { width:34%; background:rgba(255,255,255,.62); }
        .visual-lines span:nth-child(2) { width:16%; margin-inline-start:auto; }
        .visual-lines span:nth-child(3) { width:8%; }
        .visual-cards { display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-bottom:10px; }
        .visual-cards span { height:25px; border-radius:7px; background:rgba(255,255,255,.12); }
        .visual-chart { height:42px; display:flex; align-items:flex-end; gap:6px; padding:0 5px; }
        .visual-chart span { flex:1; height:var(--bar); border-radius:5px 5px 2px 2px; background:linear-gradient(180deg,#9cc2ff,rgba(156,194,255,.35)); }
        .login-hero-footer { position:relative; z-index:1; color:#c1d2f3; font-size:12px; }
        .login-form-side { min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:28px clamp(24px, 5vw, 76px); position:relative; background:radial-gradient(ellipse at 50% 0%, #fff 0%, #f7f9fd 58%, #eef3fb 100%); }
        .login-topline { width:min(100%, 470px); display:flex; align-items:center; justify-content:flex-end; margin-bottom:18px; }
        .login-mobile-brand { display:none; align-items:center; gap:10px; font-size:17px; font-weight:800; color:var(--ink); }
        .login-mobile-brand .brand-mark { width:38px; height:38px; border-radius:12px; font-size:19px; }
        .login-language { display:flex; }
        .login-language .lang-switch { border-radius:11px; box-shadow:0 3px 10px rgba(16,48,122,.05); }
        .login-card { width:100%; max-width:470px; padding:clamp(28px, 3.2vw, 42px); border:1px solid #e6ebf4; border-radius:24px; background:rgba(255,255,255,.94); box-shadow:0 24px 70px rgba(24,49,89,.1), 0 2px 8px rgba(24,49,89,.04); }
        .login-card-kicker { display:inline-flex; align-items:center; gap:8px; color:var(--blue-600); font-size:11px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; margin-bottom:12px; }
        .login-card-kicker span { width:7px; height:7px; border-radius:50%; background:var(--green); }
        .login-card h2 { font-size:30px; letter-spacing:-.035em; margin-bottom:7px; }
        .login-card .sub { color:var(--muted); font-size:14px; line-height:1.7; margin:0 0 28px; }
        .login-card .field { margin-bottom:19px; }
        .login-card .field label { color:#34445f; font-size:13px; font-weight:700; margin-bottom:8px; }
        .login-card .input { min-height:50px; border-color:#dfe6f1; border-radius:12px; background:#fbfcff; box-shadow:inset 0 1px 2px rgba(18,34,62,.025); }
        .login-card .input:hover { border-color:#b9c9e2; }
        .login-card .input:focus { background:#fff; border-color:var(--blue-400); box-shadow:0 0 0 4px rgba(91,139,245,.14); }
        .login-remember { margin:0 0 20px; }
        .login-remember input { display:block; position:absolute; opacity:0; width:1px; height:1px; }
        .login-remember:focus-within .track { outline:3px solid rgba(91,139,245,.3); outline-offset:3px; }
        .login-remember-text { font-size:13px; font-weight:650; color:var(--ink-soft); }
        .login-submit { min-height:52px; border-radius:13px; font-size:15px; font-weight:800; box-shadow:0 10px 22px rgba(34,88,201,.24); }
        .login-submit:hover { transform:translateY(-1px); box-shadow:0 13px 26px rgba(34,88,201,.3); }
        .demo-creds { margin-top:22px; padding:16px; background:#f7f9fd; border:1px solid #e8edf5; border-radius:15px; font-size:12.5px; color:var(--ink-soft); }
        .demo-creds b { color:var(--blue-700); }
        .demo-creds .row { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:5px 0; }
        .demo-creds code { max-width:72%; overflow-wrap:anywhere; direction:ltr; unicode-bidi:isolate; text-align:end; background:#fff; padding:4px 8px; border-radius:7px; border:1px solid #e4eaf3; font-size:11px; cursor:pointer; }
        .login-footer { width:min(100%, 470px); margin-top:18px; color:#8793a7; font-size:11.5px; text-align:center; }
        @media (max-width: 1100px) {
            .login-wrap { grid-template-columns:minmax(0, 1fr) minmax(390px, .95fr); }
            .login-hero { padding:38px; }
            .lh-main h1 { font-size:40px; }
            .lh-visual { height:128px; }
        }
        @media (max-width: 860px) {
            .login-wrap { display:block; }
            .login-hero { display:none; }
            .login-form-side { min-height:100vh; padding:28px 20px; }
            .login-topline { justify-content:space-between; margin-bottom:20px; }
            .login-mobile-brand { display:flex; }
            .login-card { max-width:500px; }
            .login-footer { max-width:500px; }
        }
        @media (max-width: 480px) {
            .login-form-side { padding:20px 14px 26px; }
            .login-topline { align-items:flex-start; gap:10px; }
            .login-mobile-brand { font-size:14px; }
            .login-mobile-brand .brand-mark { width:34px; height:34px; font-size:17px; }
            .login-language .lang-switch a { padding:6px 8px; font-size:11.5px; }
            .login-card { padding:25px 20px; border-radius:19px; }
            .login-card h2 { font-size:26px; }
            .demo-creds { padding:13px; }
            .demo-creds code { font-size:10px; }
        }
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
            <div class="lh-eyebrow"><span></span>{{ __('POS Terminal') }}</div>
            <h1>{!! __('Run your store,<br>not your paperwork.') !!}</h1>
            <p>{{ __('A complete point-of-sale and inventory platform built for busy supermarkets and retail counters.') }}</p>
            <div class="lh-feats">
                <div class="lh-feat"><span class="fi">@include('partials.icon',['name'=>'pos'])</span> {{ __('Fast barcode checkout & cash drawer') }}</div>
                <div class="lh-feat"><span class="fi">@include('partials.icon',['name'=>'boxes'])</span> {{ __('Live inventory & low-stock alerts') }}</div>
                <div class="lh-feat"><span class="fi">@include('partials.icon',['name'=>'shield'])</span> {{ __('Role-based access & secure backups') }}</div>
            </div>
            <div class="lh-visual" aria-hidden="true">
                <div class="visual-rail"><span></span><span></span><span></span><span></span></div>
                <div class="visual-panel">
                    <div class="visual-lines"><span></span><span></span><span></span></div>
                    <div class="visual-cards"><span></span><span></span><span></span></div>
                    <div class="visual-chart"><span style="--bar:42%"></span><span style="--bar:68%"></span><span style="--bar:52%"></span><span style="--bar:88%"></span><span style="--bar:62%"></span><span style="--bar:100%"></span><span style="--bar:76%"></span><span style="--bar:92%"></span></div>
                </div>
            </div>
        </div>
        <div class="login-hero-footer">© {{ date('Y') }} {{ setting('shop_name','SuperShop') }}. {{ __('All rights reserved.') }}</div>
    </div>

    <div class="login-form-side">
        <div class="login-topline">
            <div class="login-mobile-brand"><span class="brand-mark">S</span>{{ setting('shop_name', 'SuperShop') }}</div>
            <div class="login-language">
                <div class="lang-switch">
                    <a href="{{ route('locale.switch','en') }}" class="{{ app()->getLocale() === 'en' ? 'on' : '' }}">English</a>
                    <a href="{{ route('locale.switch','prs') }}" class="{{ app()->getLocale() === 'prs' ? 'on' : '' }}">دری</a>
                    <a href="{{ route('locale.switch','ps') }}" class="{{ app()->getLocale() === 'ps' ? 'on' : '' }}">پښتو</a>
                </div>
            </div>
        </div>
        <div class="login-card">
            <div class="login-card-kicker"><span></span>{{ __('Sign in') }}</div>
            <h2>{{ __('Welcome back') }}</h2>
            <p class="sub">{{ __('Sign in to your account to continue.') }}</p>

            @if($errors->any())
                <div class="alert alert-error">@include('partials.icon',['name'=>'alert']) {{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="field">
                    <label for="email">{{ __('Email address') }}</label>
                    <input type="email" name="email" id="email" class="input input-lg" value="{{ old('email') }}" placeholder="you@store.com" autocomplete="username" required autofocus>
                </div>
                <div class="field">
                    <label for="password">{{ __('Password') }}</label>
                    <input type="password" name="password" id="password" class="input input-lg" placeholder="••••••••" autocomplete="current-password" required>
                </div>
                <label class="switch login-remember">
                    <input type="checkbox" name="remember">
                    <span class="track"></span>
                    <span class="login-remember-text">{{ __('Keep me signed in') }}</span>
                </label>
                <button type="submit" class="btn btn-primary btn-block btn-lg login-submit">
                    {{ __('Sign in') }} @include('partials.icon',['name'=>'arrow-left','class'=>''])
                </button>
            </form>
        </div>
        <div class="login-footer">© {{ date('Y') }} {{ setting('shop_name','SuperShop') }}. {{ __('All rights reserved.') }}</div>
    </div>
</div>
</body>
</html>
