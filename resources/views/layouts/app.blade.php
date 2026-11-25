<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'en' ? 'ltr' : 'rtl' }}" data-confirm="{{ __('Are you sure?') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ setting('shop_name', 'SuperShop') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;550;600;650;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
@php $u = auth()->user(); @endphp
@php
    $currentBranch = \App\Models\Branch::find(\App\Support\BranchContext::id());
    $activeBranches = $u->hasRole('Super Admin')
        ? \App\Models\Branch::where('is_active', true)->orderBy('branch_number')->get()
        : collect();
    $unreadNotifications = $u->unreadNotifications()->count();
    $unreadMessages = \Illuminate\Support\Facades\DB::table('messages')
        ->join('message_thread_participants', 'message_thread_participants.message_thread_id', '=', 'messages.message_thread_id')
        ->where('message_thread_participants.user_id', $u->id)
        ->where(function ($query) use ($u) {
            $query->whereNull('messages.sender_id')
                ->orWhere('messages.sender_id', '!=', $u->id);
        })
        ->where(function ($query) {
            $query->whereNull('message_thread_participants.last_read_at')
                ->orWhereColumn('messages.created_at', '>', 'message_thread_participants.last_read_at');
        })
        ->count();
@endphp
<div
    class="app"
    data-presence-root
    data-presence-branches="{{ $u->branch_id }}"
    data-role-labels="{{ json_encode(['Admin' => __('Admin'), 'Super Admin' => __('Super Admin'), 'Manager' => __('Manager'), 'Cashier' => __('Cashier'), 'User' => __('User')], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}"
>
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark">S</div>
            <div>
                <div class="brand-name">{{ setting('shop_name', 'SuperShop') }}</div>
                <div class="brand-sub">POS &amp; Management</div>
            </div>
        </div>
        <nav class="nav">
            @php
                $link = function ($route, $icon, $label, $perm = null, $badge = null) use ($u) {
                    if ($perm && ! $u->can($perm)) return '';
                    $active = request()->routeIs($route) || request()->routeIs(str_replace('.index','.*',$route));
                    $href = route($route);
                    $b = $badge ? '<span class="nav-badge">'.$badge.'</span>' : '';
                    $svg = view('partials.icon', ['name' => $icon])->render();
                    return '<a href="'.$href.'" class="nav-link'.($active?' active':'').'">'.$svg.'<span>'.__($label).'</span>'.$b.'</a>';
                };
                $lowStock = \App\Models\Product::query()->active()->lowStock()->count();
            @endphp

            <div class="nav-section">{{ __('Main') }}</div>
            {!! $link('dashboard', 'dashboard', 'Dashboard', 'dashboard.view') !!}
            {!! $link('messages.index', 'inbox', 'Messages') !!}
            {!! $link('notifications.index', 'bell', 'Notifications', null, $unreadNotifications ?: null) !!}
            {!! $link('pos.index', 'pos', 'POS Terminal', 'pos.access') !!}
            {!! $link('register.index', 'wallet', 'Cash Register', 'cash.manage') !!}

            @if($u->canAny(['products.view','categories.manage','units.manage','inventory.view','suppliers.manage']))
                <div class="nav-section">{{ __('Catalog') }}</div>
                {!! $link('products.index', 'box', 'Products', 'products.view') !!}
                {!! $link('categories.index', 'tag', 'Categories', 'categories.manage') !!}
                {!! $link('units.index', 'ruler', 'Units', 'units.manage') !!}
                {!! $link('inventory.index', 'boxes', 'Inventory', 'inventory.view', $lowStock ?: null) !!}
                {!! $link('suppliers.index', 'truck', 'Suppliers', 'suppliers.manage') !!}
            @endif

            @if($u->can('inventory.transfers'))
                <div class="nav-section">{{ __('Logistics') }}</div>
                {!! $link('transfers.index', 'truck', 'Stock Transfers', 'inventory.transfers') !!}
            @endif

            @if($u->canAny(['sales.view','purchases.view','customers.view','expenses.view']))
                <div class="nav-section">{{ __('Operations') }}</div>
                {!! $link('sales.index', 'receipt', 'Sales', 'sales.view') !!}
                {!! $link('purchases.index', 'inbox', 'Purchases', 'purchases.view') !!}
                {!! $link('customers.index', 'users', 'Customers', 'customers.view') !!}
                {!! $link('expenses.index', 'wallet', 'Expenses', 'expenses.view') !!}
            @endif

            @if($u->can('reports.view'))
                <div class="nav-section">{{ __('Insights') }}</div>
                {!! $link('reports.sales', 'chart', 'Sales Report', 'reports.view') !!}
                {!! $link('reports.profit', 'trend', 'Profit Report', 'reports.view') !!}
                {!! $link('reports.inventory', 'boxes', 'Stock Report', 'reports.view') !!}
                {!! $link('reports.top', 'star', 'Top Products', 'reports.view') !!}
            @endif

            @if($u->canAny(['users.manage','roles.manage','settings.manage','backups.manage','activity.view']))
                <div class="nav-section">{{ __('Administration') }}</div>
                {!! $link('users.index', 'user', 'Users', 'users.manage') !!}
                {!! $link('roles.index', 'shield', 'Roles &amp; Access', 'roles.manage') !!}
                @if($u->hasRole('Super Admin'))
                    {!! $link('branches.index', 'bag', 'Branches') !!}
                @endif
                {!! $link('settings.edit', 'settings', 'Settings', 'settings.manage') !!}
                {!! $link('backups.index', 'database', 'Backups', 'backups.manage') !!}
                {!! $link('activity.index', 'activity', 'Activity Log', 'activity.view') !!}
            @endif
        </nav>
        <a class="sidebar-profile{{ request()->routeIs('profile.*') ? ' active' : '' }}" href="{{ route('profile.edit') }}" aria-label="{{ __('My Profile') }}: {{ $u->name }}">
            @include('partials.user-avatar', ['user' => $u, 'class' => 'sidebar-profile-avatar'])
            <span class="sidebar-profile-copy">
                <span class="sidebar-profile-name">{{ $u->name }}</span>
                <span class="sidebar-profile-label">{{ __('My Profile') }}</span>
            </span>
        </a>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')">
                @include('partials.icon', ['name' => 'menu'])
            </button>
            <div>
                <h1>{{ __(trim($__env->yieldContent('title', 'Dashboard'))) }}</h1>
                @hasSection('crumb')<div class="crumb">{{ __(trim($__env->yieldContent('crumb'))) }}</div>@endif
            </div>
            <div class="topbar-right">
                <a class="icon-btn message-shortcut" href="{{ route('messages.index') }}" aria-label="{{ __('Messages') }}">
                    @include('partials.icon', ['name' => 'message'])
                    @if($unreadMessages > 0)
                        <span class="message-shortcut-count">{{ $unreadMessages > 99 ? '99+' : $unreadMessages }}</span>
                    @endif
                </a>
                <a class="icon-btn notification-bell" href="{{ route('notifications.index') }}" aria-label="{{ __('Notifications') }}">
                    @include('partials.icon', ['name' => 'bell'])
                    @if($unreadNotifications > 0)
                        <span class="notification-bell-count">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                    @endif
                </a>
                @if($u->hasRole('Super Admin'))
                    <form method="POST" action="{{ route('branches.switch') }}">
                        @csrf
                        <select class="select" name="branch_id" aria-label="{{ __('Active branch') }}" onchange="this.form.submit()">
                            @foreach($activeBranches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) ($currentBranch?->id) === (int) $branch->id)>
                                    {{ $branch->branch_number }} · {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @elseif($currentBranch)
                    <span class="badge badge-blue">{{ $currentBranch->branch_number }} · {{ $currentBranch->name }}</span>
                @endif
                <div class="lang-switch">
                    <a href="{{ route('locale.switch','en') }}" class="{{ app()->getLocale() === 'en' ? 'on' : '' }}">English</a>
                    <a href="{{ route('locale.switch','prs') }}" class="{{ app()->getLocale() === 'prs' ? 'on' : '' }}">دری</a>
                    <a href="{{ route('locale.switch','ps') }}" class="{{ app()->getLocale() === 'ps' ? 'on' : '' }}">پښتو</a>
                </div>
                @can('pos.access')
                    <a href="{{ route('pos.index') }}" class="pos-btn">
                        @include('partials.icon', ['name' => 'pos']) {{ __('Open POS') }}
                    </a>
                @endcan
                <a href="{{ route('profile.edit') }}" class="user-chip">
                    @include('partials.user-avatar', ['user' => $u])
                    <div>
                        <div class="u-name">{{ $u->name }}</div>
                        <div class="u-role">{{ $u->role_name }}</div>
                    </div>
                </a>
                <button type="button" class="icon-btn danger" title="{{ __('Log out') }}" aria-label="{{ __('Log out') }}" aria-haspopup="dialog" aria-controls="logoutConfirmation" onclick="SS.openModal('logoutConfirmation')">
                    @include('partials.icon', ['name' => 'logout'])
                </button>
            </div>
        </header>

        <main class="content @yield('content-class')">
            @if(session('success'))
                <div class="alert alert-success">@include('partials.icon', ['name' => 'check-circle']) {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">@include('partials.icon', ['name' => 'alert']) {{ session('error') }}</div>
            @endif
            @if($errors->any() && ! $errors->has('__pos'))
                <div class="alert alert-error">
                    @include('partials.icon', ['name' => 'alert'])
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @yield('content')
        </main>
        <footer class="app-footer">
            <span>{{ setting('shop_name', 'SuperShop') }}</span>
            <span>{{ __('System version') }} <strong>v{{ config('app.version') }}</strong></span>
        </footer>
    </div>
</div>
<div class="modal-overlay" id="logoutConfirmation" style="display:none">
    <section class="modal logout-modal" role="dialog" aria-modal="true" aria-labelledby="logoutTitle" aria-describedby="logoutDescription">
        <div class="logout-modal-icon">@include('partials.icon', ['name' => 'logout'])</div>
        <h2 id="logoutTitle">{{ __('Sign out?') }}</h2>
        <p id="logoutDescription">{{ __('Are you sure you want to sign out of your account?') }}</p>
        <div class="logout-modal-actions">
            <button type="button" class="btn btn-light" data-modal-autofocus onclick="SS.closeModal('logoutConfirmation')">{{ __('Stay signed in') }}</button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-danger">@include('partials.icon', ['name' => 'logout']) {{ __('Log out') }}</button>
            </form>
        </div>
    </section>
</div>
<script src="{{ asset('js/app.js') }}"></script>
@vite('resources/js/app.js')
@stack('scripts')
</body>
</html>
