<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    protected $fillable = ['sender_id', 'recipient_id', 'group_id', 'body', 'attachments'];

    protected $casts = ['attachments' => 'array'];

    protected $appends = ['reactions_summary'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TktGroup::class, 'group_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ChatMessageReaction::class, 'chat_message_id');
    }

    /**
     * @return array<int, array{emoji: string, count: int, user_ids: array<int, int>, users: array<int, array{id: int, name: string}>}>
     */
    public function getReactionsSummaryAttribute(): array
    {
        if (! $this->relationLoaded('reactions')) {
            return [];
        }

        return $this->reactions
            ->groupBy('emoji')
            ->map(function ($group, $emoji) {
                return [
                    'emoji' => (string) $emoji,
                    'count' => $group->count(),
                    'user_ids' => $group->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all(),
                    'users' => $group->map(function ($reaction) {
                        $u = $reaction->user;

                        return [
                            'id' => (int) $reaction->user_id,
                            'name' => $u?->fullname ?: ($u?->name ?: $u?->email ?: 'User'),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
