@php
    $otherParticipants = $thread->participants->where('id', '!=', $currentUserId);
    $threadName = $thread->type === 'announcement'
        ? $thread->subject
        : $otherParticipants->pluck('name')->join(', ');
    $latestMessage = $thread->latestMessage;
    $previewSender = $latestMessage && (int) $latestMessage->sender_id === (int) $currentUserId
        ? __('You')
        : ($latestMessage?->sender_name ?? '');
    $preview = $latestMessage
        ? trim($previewSender.': '.$latestMessage->body)
        : __('No messages yet');
    $unreadCount = (int) $thread->unread_count;
@endphp
<a class="chat-thread-row {{ (int) $thread->id === (int) ($activeThreadId ?? 0) ? 'is-active' : '' }}"
   href="{{ route('messages.show', $thread) }}"
   data-thread-row
   data-thread-id="{{ $thread->id }}"
   data-thread-url="{{ route('messages.show', $thread) }}"
   data-thread-name="{{ $threadName }}"
   data-last-message-time="{{ $latestMessage?->created_at?->toIso8601String() }}"
   aria-current="{{ (int) $thread->id === (int) ($activeThreadId ?? 0) ? 'page' : 'false' }}">
    <span class="chat-avatar {{ $thread->type === 'announcement' ? 'is-announcement' : '' }}">
        @if($thread->type === 'announcement')
            @include('partials.icon', ['name' => 'users'])
        @else
            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($threadName ?: '?', 0, 1)) }}
        @endif
    </span>
    <span class="chat-thread-details">
        <span class="chat-thread-topline">
            <strong data-thread-title>{{ $threadName }}</strong>
            <time data-thread-time>{{ $latestMessage?->created_at?->translatedFormat('h:i A') }}</time>
        </span>
        <span class="chat-thread-bottomline">
            <span class="chat-thread-preview" data-thread-preview>{{ \Illuminate\Support\Str::limit($preview, 62) }}</span>
            <span class="chat-unread-count" data-thread-unread @if($unreadCount === 0) hidden @endif>{{ $unreadCount }}</span>
        </span>
    </span>
</a>
