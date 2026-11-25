@extends('layouts.app')
@section('title', 'Messages')
@section('crumb', $thread->type === 'announcement' ? $thread->subject : $thread->participants->where('id', '!=', auth()->id())->pluck('name')->join(', '))
@section('content-class', 'message-content')

@section('content')
@php
    $otherParticipants = $thread->participants->where('id', '!=', auth()->id());
    $conversationTitle = $thread->type === 'announcement'
        ? $thread->subject
        : $otherParticipants->pluck('name')->join(', ');
    $counterpart = $otherParticipants->first();
@endphp

<div class="chat-layout chat-layout-active"
     data-chat-conversation
     data-thread-id="{{ $thread->id }}"
     data-user-id="{{ auth()->id() }}"
     data-messages-url="{{ url('/messages') }}"
     data-thread-type="{{ $thread->type }}"
     data-read-url="{{ route('messages.read', $thread) }}"
     data-reply-url="{{ route('messages.reply', $thread) }}"
     data-can-reply="{{ $canReply ? 'true' : 'false' }}"
     data-label-you="{{ __('You') }}"
     data-label-sent="{{ __('Sent') }}"
     data-label-seen="{{ __('Seen') }}"
     data-label-live="{{ __('Live') }}"
     data-label-connecting="{{ __('Connecting...') }}"
     data-label-offline="{{ __('Reconnecting...') }}"
     data-label-send-error="{{ __('Unable to send the message.') }}">
    <aside class="chat-sidebar">
        <div class="chat-sidebar-heading">
            <div class="chat-sidebar-title-row">
                <h3>{{ __('Chats') }}</h3>
                <a class="icon-btn" href="{{ route('messages.compose') }}" aria-label="{{ __('New chat') }}" title="{{ __('New chat') }}">
                    @include('partials.icon', ['name' => 'plus'])
                </a>
            </div>
            <label class="chat-search">
                @include('partials.icon', ['name' => 'search'])
                <input class="input" type="search" placeholder="{{ __('Search conversations') }}" data-chat-search>
            </label>
        </div>
        <div class="chat-thread-list" data-chat-list>
            @forelse($threads as $chatThread)
                @include('messages._thread-row', ['thread' => $chatThread, 'currentUserId' => auth()->id(), 'activeThreadId' => $thread->id])
            @empty
                <p class="chat-list-empty">{{ __('No conversations yet.') }}</p>
            @endforelse
        </div>
    </aside>

    <section class="chat-panel">
        <header class="chat-conversation-header">
            <a class="chat-mobile-back" href="{{ route('messages.index') }}" aria-label="{{ __('Back to chats') }}">
                @include('partials.icon', ['name' => 'arrow-left'])
            </a>
            <span class="chat-avatar {{ $thread->type === 'announcement' ? 'is-announcement' : '' }}">
                @if($thread->type === 'announcement')
                    @include('partials.icon', ['name' => 'users'])
                @else
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($conversationTitle ?: '?', 0, 1)) }}
                @endif
            </span>
            <div class="chat-conversation-identity">
                <strong>{{ $conversationTitle }}</strong>
                <span>{{ $thread->type === 'announcement' ? __('Branch announcement') : ($counterpart?->role_name ?? __('Direct message')) }}</span>
            </div>
            <span class="chat-connection-status" data-chat-connection role="status">{{ __('Connecting...') }}</span>
        </header>

        <div class="chat-message-list" data-message-list aria-live="polite">
            @foreach($thread->messages as $message)
                @php
                    $isOwnMessage = (int) $message->sender_id === (int) auth()->id();
                    $peerReadAt = $counterpart?->pivot?->last_read_at;
                    $messageSeen = $isOwnMessage && $thread->type === 'direct' && $peerReadAt
                        ? \Illuminate\Support\Carbon::parse($peerReadAt)->greaterThanOrEqualTo($message->created_at)
                        : false;
                @endphp
                <article class="chat-message {{ $isOwnMessage ? 'is-own' : '' }}"
                         data-message-id="{{ $message->id }}"
                         data-sender-id="{{ $message->sender_id }}"
                         data-created-at="{{ $message->created_at->toIso8601String() }}">
                    @unless($isOwnMessage)
                        <strong class="chat-message-sender">{{ $message->sender_name }}</strong>
                    @endunless
                    <p class="chat-message-body">{{ $message->body }}</p>
                    <div class="chat-message-meta">
                        <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->translatedFormat('h:i A') }}</time>
                        @if($isOwnMessage && $thread->type === 'direct')
                            <span class="chat-message-status" data-message-status>{{ $messageSeen ? __('Seen') : __('Sent') }}</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if($canReply)
            <form class="chat-composer" method="POST" action="{{ route('messages.reply', $thread) }}" data-message-form>
                @csrf
                <div class="chat-compose-error" data-compose-error role="alert" hidden></div>
                <textarea class="input" name="body" rows="1" maxlength="5000" placeholder="{{ __('Write a message...') }}" aria-label="{{ __('Write a message') }}" required data-message-input>{{ old('body') }}</textarea>
                <button class="btn btn-primary" type="submit" aria-label="{{ __('Send message') }}" title="{{ __('Send message') }}">
                    @include('partials.icon', ['name' => 'send'])
                </button>
                @error('body')<div class="field-error">{{ $message }}</div>@enderror
            </form>
        @else
            <div class="chat-announcement-note">{{ __('This announcement is read-only.') }}</div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
    @vite('resources/js/app.js')
@endpush
