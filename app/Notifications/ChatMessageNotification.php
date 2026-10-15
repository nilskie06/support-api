<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChatMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public ChatMessage $chatMessage) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $sender = $this->chatMessage->sender;
        $senderName = $sender?->fullname ?: ($sender?->name ?: $sender?->email ?: 'A user');
        $groupName = $this->chatMessage->group_id ? $this->chatMessage->group?->name : null;
        $conversationQuery = $this->chatMessage->group_id
            ? 'team='.$this->chatMessage->group_id
            : 'user='.$this->chatMessage->sender_id;

        return [
            'kind' => 'chat',
            'message' => $this->chatMessage->body ?: 'Sent an attachment',
            'subject' => $groupName ? "{$senderName} · {$groupName}" : $senderName,
            'sender_id' => $this->chatMessage->sender_id,
            'group_id' => $this->chatMessage->group_id,
            'group_name' => $groupName,
            'chat_message_id' => $this->chatMessage->id,
            'url' => '/chat?'.$conversationQuery,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return 'chat.message';
    }
}
