<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public array $recipientIds,
        public string $threadType,
        public string $threadTitle,
    ) {}

    public function broadcastOn(): array
    {
        return array_merge(
            [new PrivateChannel('messages.thread.'.$this->message->message_thread_id)],
            array_map(
                fn (int $userId): PrivateChannel => new PrivateChannel('messages.user.'.$userId),
                $this->recipientIds,
            ),
        );
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'thread_id' => $this->message->message_thread_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender_name,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at->toIso8601String(),
            'thread_type' => $this->threadType,
            'thread_title' => $this->threadTitle,
        ];
    }
}
