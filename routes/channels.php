<?php

use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('messages.thread.{threadId}', function (User $user, int $threadId): bool {
    return $user->is_active && MessageThread::query()
        ->whereKey($threadId)
        ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
        ->exists();
});

Broadcast::channel('messages.user.{userId}', function (User $user, int $userId): bool {
    return $user->is_active && (int) $user->id === $userId;
});
