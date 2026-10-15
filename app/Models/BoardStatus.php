<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'order',
    'name',
    'color',
    'type',
    'is_required',
    'has_next_step',
    'notify_creator',
    'notify_followers',
    'notify_assignees',
    'board_id',
    'created_by',
    'updated_by',
])]
class BoardStatus extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tkt_board_status';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_required' => 'boolean',
            'has_next_step' => 'boolean',
            'notify_creator' => 'boolean',
            'notify_followers' => 'boolean',
            'notify_assignees' => 'boolean',
        ];
    }

    /**
     * Associated tickets.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'board_status_id');
    }

    /**
     * Parent board relationship.
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class, 'board_id');
    }
}
