@extends('layouts.app')
@section('title', 'Messages')
@section('crumb', 'Internal team messages')

@section('content')
<div class="chat-page-heading">
    <div>
        <h2>{{ __('Chats') }}</h2>
        <p>{{ __('Private conversations and branch announcements.') }}</p>
    </div>
    <a class="btn btn-primary" href="{{ route('messages.compose') }}">
        @include('partials.icon', ['name' => 'plus']) {{ __('New chat') }}
    </a>
</div>

<div class="chat-layout" data-chat-inbox
     data-user-id="{{ auth()->id() }}"
     data-label-you="{{ __('You') }}"
     data-label-sent="{{ __('Sent') }}"
     data-label-seen="{{ __('Seen') }}"
     data-label-live="{{ __('Live') }}"
     data-label-connecting="{{ __('Connecting...') }}"
     data-label-offline="{{ __('Reconnecting...') }}">
    <aside class="chat-sidebar">
        <div class="chat-sidebar-heading">
            <h3>{{ __('Messages') }}</h3>
            <label class="chat-search">
                @include('partials.icon', ['name' => 'search'])
                <input class="input" type="search" placeholder="{{ __('Search conversations') }}" data-chat-search>
            </label>
        </div>
        <div class="chat-thread-list" data-chat-list>
            @forelse($threads as $thread)
                @include('messages._thread-row', ['thread' => $thread, 'currentUserId' => auth()->id()])
            @empty
                <p class="chat-list-empty">{{ __('No conversations yet. Start a new chat.') }}</p>
            @endforelse
        </div>
        @if($threads->hasPages())
            <div class="chat-list-pagination">{{ $threads->links('partials.pagination') }}</div>
        @endif
    </aside>

    <section class="chat-panel chat-welcome-panel">
        <div class="chat-welcome">
            @include('partials.icon', ['name' => 'inbox'])
            <h3>{{ __('Your conversations') }}</h3>
            <p>{{ __('Choose a chat to continue or start a new conversation with your team.') }}</p>
            <a class="btn btn-primary" href="{{ route('messages.compose') }}">{{ __('Start a chat') }}</a>
        </div>
    </section>
</div>
@endsection

@push('scripts')
    @vite('resources/js/app.js')
@endpush
