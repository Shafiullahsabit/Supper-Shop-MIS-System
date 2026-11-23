<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\MessageThread;
use App\Models\User;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('messages.thread.{threadId}', function (User $user, int $threadId): bool {
    return $user->is_active && MessageThread::query()
        ->whereKey($threadId)
        ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
        ->exists();
});
