<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'ref_no',
    'subject',
    'summary',
    'date_encountered',
    'frequency',
    'duration_from',
    'duration_to',
    'url',
    'system',
    'module',
    'issue_group',
    'order',
    'board_id',
    'board_status_id',
    'parent_id',
    'issue_type_id',
    'tkt_type_id',
    'type_other',
    'issue_severity_id',
    'department_id',
    'group_id',
    'workflow_action',
    'company_id',
    'is_archived',
    'status',
    'closed_by',
    'closed_at',
    'last_followed_up_at',
    'follow_up_count',
    'created_by',
    'updated_by',
])]
class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tkt_system_tickets';

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'status_name',
        'status_color',
        'severity_name',
        'severity_color',
        'type_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_encountered' => 'date:Y-m-d',
            'duration_from' => 'date:Y-m-d',
            'duration_to' => 'date:Y-m-d',
            'closed_at' => 'datetime',
            'last_followed_up_at' => 'datetime',
            'is_archived' => 'boolean',
            'status' => 'integer',
            'follow_up_count' => 'integer',
        ];
    }

    /**
     * Board status relationship.
     */
    public function boardStatus(): BelongsTo
    {
        return $this->belongsTo(BoardStatus::class, 'board_status_id');
    }

    /**
     * Ticket category / board.
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class, 'board_id');
    }

    /**
     * Issue type relationship.
     */
    public function issueType(): BelongsTo
    {
        return $this->belongsTo(IssueType::class, 'issue_type_id', 'issue_id');
    }

    /**
     * Issue severity relationship.
     */
    public function issueSeverity(): BelongsTo
    {
        return $this->belongsTo(IssueSeverity::class, 'issue_severity_id');
    }

    /**
     * Ticket creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who closed the ticket.
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Assigned users.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tkt_system_ticket_assignee', 'system_ticket_id', 'user_id');
    }

    /**
     * Users following the ticket for updates.
     */
    public function taggedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tkt_system_ticket_tagged_user', 'system_ticket_id', 'user_id');
    }

    /**
     * Groups following the ticket for updates.
     */
    public function taggedGroups(): BelongsToMany
    {
        return $this->belongsToMany(TktGroup::class, 'tkt_system_ticket_tagged_group', 'system_ticket_id', 'group_id');
    }

    /**
     * Assigned group relationship.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TktGroup::class, 'group_id');
    }

    /**
     * File attachments.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'system_ticket_id');
    }

    /**
     * Comments and follow-ups recorded against this ticket.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'system_ticket_id')
            ->orderBy('created_at');
    }

    /**
     * Ticket resolution details.
     */
    public function resolutions(): HasMany
    {
        return $this->hasMany(TicketResolution::class, 'ticket_id')->latest();
    }

    /**
     * Board specific ticket type (Facilities, etc.).
     */
    public function tktType(): BelongsTo
    {
        return $this->belongsTo(TktType::class, 'tkt_type_id');
    }

    /**
     * Status display name.
     */
    protected function statusName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->boardStatus?->name ?? match ($this->status) {
                0 => 'Closed',
                1 => 'New',
                2 => 'Open',
                3 => 'Inprogress',
                default => 'Open',
            }
        );
    }

    /**
     * Status color class or token.
     */
    protected function statusColor(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->boardStatus?->color ?? match ($this->status) {
                0 => 'danger',
                1 => 'info',
                2 => 'primary',
                3 => 'warning',
                default => 'primary',
            }
        );
    }

    /**
     * Severity name.
     */
    protected function severityName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->issueSeverity?->name ?? 'Medium'
        );
    }

    /**
     * Severity color.
     */
    protected function severityColor(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->issueSeverity?->color ?? 'warning'
        );
    }

    /**
     * Issue type name.
     */
    protected function typeName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->issueType?->issue_desc ?? 'General'
        );
    }
}
