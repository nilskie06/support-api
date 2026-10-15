<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'color',
    'enable',
])]
class IssueSeverity extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tkt_issue_severities';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enable' => 'boolean',
        ];
    }

    /**
     * Associated tickets.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'issue_severity_id');
    }
}
