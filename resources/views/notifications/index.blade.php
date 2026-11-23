@extends('layouts.app')
@section('title', 'Notifications')
@section('crumb', 'Operational updates for your account')

@section('content')
<div class="page-head">
    <div class="titles">
        <h2>{{ __('Notifications') }}</h2>
        <p>{{ __('Important updates for your branch and account.') }}</p>
    </div>
    @if($unreadCount > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="btn btn-light">@include('partials.icon', ['name' => 'check']) {{ __('Mark all as read') }}</button>
        </form>
    @endif
</div>

<div class="notification-toolbar">
    <div class="notification-filters">
        <a href="{{ route('notifications.index') }}" class="btn {{ $filter === 'all' ? 'btn-primary' : 'btn-light' }}">{{ __('All') }}</a>
        <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="btn {{ $filter === 'unread' ? 'btn-primary' : 'btn-light' }}">
            {{ __('Unread') }} <span class="notification-filter-count">{{ $unreadCount }}</span>
        </a>
    </div>
</div>

<div class="notification-list">
    @forelse($notifications as $notification)
        @php($data = $notification->data)
        @php($notificationTitle = __($data['title_key'] ?? $data['title'] ?? 'Notification', $data['title_params'] ?? []))
        @php($notificationMessage = __($data['message_key'] ?? $data['message'] ?? '', $data['message_params'] ?? []))
        <article class="card notification-card {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
            <div class="notification-icon">
                @include('partials.icon', ['name' => $data['icon'] ?? 'info'])
            </div>
            <div class="notification-copy">
                <div class="notification-title-row">
                    <h3>{{ $notificationTitle }}</h3>
                    @unless($notification->read_at)
                        <span class="notification-unread-dot" aria-label="{{ __('Unread') }}"></span>
                    @endunless
                </div>
                <p>{{ $notificationMessage }}</p>
                <time datetime="{{ $notification->created_at->toIso8601String() }}">
                    {{ $notification->created_at->translatedFormat('d M Y, h:i A') }}
                </time>
            </div>
            <div class="notification-actions">
                @if(! empty($data['url']))
                    <a class="btn btn-light" href="{{ $data['url'] }}">@include('partials.icon', ['name' => 'arrow-right']) {{ __('Open') }}</a>
                @endif
                @unless($notification->read_at)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                        @csrf
                        <button class="btn btn-light">{{ __('Mark as read') }}</button>
                    </form>
                @endunless
            </div>
        </article>
    @empty
        <div class="card notification-empty">
            @include('partials.icon', ['name' => 'bell'])
            <h3>{{ __("You're all caught up") }}</h3>
            <p>{{ __('New operational updates will appear here.') }}</p>
        </div>
    @endforelse
</div>

<div class="notification-pagination">{{ $notifications->links('partials.pagination') }}</div>
@endsection
