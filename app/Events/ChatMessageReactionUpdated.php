<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageReactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, array{emoji: string, count: int, user_ids: array<int, int>, users: array<int, array{id: int, name: string}>}>  $reactionsSummary
     */
    public function __construct(
        public ChatMessage $message,
        public array $reactionsSummary
    ) {}

    public function broadcastOn(): array
    {
        if ($this->message->group_id) {
            return [new PrivateChannel('chat.team.'.$this->message->group_id)];
        }

        return [
            new PrivateChannel('chat.user.'.$this->message->sender_id),
            new PrivateChannel('chat.user.'.$this->message->recipient_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.reaction';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'group_id' => $this->message->group_id,
            'sender_id' => $this->message->sender_id,
            'recipient_id' => $this->message->recipient_id,
            'reactions' => $this->reactionsSummary,
        ];
    }
}
