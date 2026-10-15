<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'issue_desc',
    'color',
    'board_id',
])]
class IssueType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tkt_issue_types';

    /**
     * Primary key column.
     *
     * @var string
     */
    protected $primaryKey = 'issue_id';

    /**
     * Associated tickets.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'issue_type_id', 'issue_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class, 'board_id');
    }
}
