<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $threadId,
        public int $userId,
        public string $userName,
        public string $readAt,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('messages.thread.'.$this->threadId)];
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }

    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->threadId,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'read_at' => $this->readAt,
        ];
    }
}
