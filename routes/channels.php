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

Broadcast::channel('messages.online.{branchId}', function (User $user, int $branchId): array|bool {
    if (! $user->is_active) {
        return false;
    }

    $canViewBranch = (int) $user->branch_id === $branchId
        || MessageThread::query()
            ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
            ->whereHas('participants', fn ($query) => $query->where('users.branch_id', $branchId))
            ->exists();

    if (! $canViewBranch) {
        return false;
    }

    return [
        'id' => (int) $user->id,
        'name' => $user->name,
        'role' => $user->role_name,
        'branch_id' => (int) $user->branch_id,
    ];
});
