<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardStatus;
use App\Models\Company;
use App\Models\Department;
use App\Models\IssueSeverity;
use App\Models\IssueType;
use App\Models\Menu;
use App\Models\Station;
use App\Models\System;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TktGroup;
use App\Models\TktType;
use App\Models\User;
use App\Notifications\TicketActivityNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets from tkt_system_tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $query = Ticket::query();
        $this->scopeTicketsVisibleToUser($query, $user);
        $visibleTickets = clone $query;

        $query->with([
            'board:id,name',
            'boardStatus.board:id,name',
            'issueType',
            'tktType',
            'issueSeverity',
            'creator:id,name,fullname,email,image',
            'creator.assignedStations:id,user_id,reference,reference_code,default',
            'creator.assignedDepartments:id,user_id,reference,reference_code,default',
            'assignees:id,name,fullname,email,image,role',
            'group:id,name',
            'group.members:id,name,fullname,email,image,role',
            'taggedUsers:id,name,fullname,email,image,role',
            'taggedGroups:id,name',
            'taggedGroups.members:id,name,fullname,email,image,role',
        ]);

        // Search only by reference number, subject, or summary.
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('ref_no', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        // Board filter (by board_id, board name, or board statuses)
        if ($board = $request->input('board') ?? $request->input('board_id')) {
            if ($board !== 'All' && $board !== '') {
                if (is_numeric($board)) {
                    $query->where(function (Builder $q) use ($board) {
                        $q->where('board_id', (int) $board)
                            ->orWhere(function (Builder $legacyQuery) use ($board) {
                                $legacyQuery->whereNull('board_id')
                                    ->whereHas('boardStatus', function (Builder $statusQuery) use ($board) {
                                        $statusQuery->where('board_id', (int) $board);
                                    });
                            });
                    });
                } else {
                    $query->where(function (Builder $q) use ($board) {
                        $q->whereHas('boardStatus.board', function (Builder $b) use ($board) {
                            $b->where('name', 'like', "%{$board}%");
                        })->orWhere('system', 'like', "%{$board}%");
                    });
                }
            }
        }

        // Status filter (by board_status_id or status name)
        if ($status = $request->input('status')) {
            if ($status !== 'All') {
                if (is_numeric($status)) {
                    $query->where('board_status_id', (int) $status);
                } else {
                    $query->whereHas('boardStatus', function (Builder $q) use ($status) {
                        $q->where('name', $status);
                    });
                }
            }
        }

        // Severity / Priority filter
        if ($severity = $request->input('severity') ?? $request->input('priority')) {
            if ($severity !== 'All') {
                if (is_numeric($severity)) {
                    $query->where('issue_severity_id', (int) $severity);
                } else {
                    $query->whereHas('issueSeverity', function (Builder $q) use ($severity) {
                        $q->where('name', $severity);
                    });
                }
            }
        }

        // Issue type filter
        if ($type = $request->input('type')) {
            if ($type !== 'All') {
                if (is_numeric($type)) {
                    $query->where('issue_type_id', (int) $type);
                } else {
                    $query->whereHas('issueType', function (Builder $q) use ($type) {
                        $q->where('issue_desc', $type);
                    });
                }
            }
        }

        // Date range filter
        if ($dateRange = $request->input('date')) {
            if (str_contains($dateRange, ' to ')) {
                [$start, $end] = explode(' to ', $dateRange);
                try {
                    $startDate = Carbon::parse($start)->startOfDay();
                    $endDate = Carbon::parse($end)->endOfDay();
                    $query->whereBetween('date_encountered', [$startDate, $endDate]);
                } catch (\Throwable) {
                    // Ignore date parse errors
                }
            } else {
                try {
                    $singleDate = Carbon::parse($dateRange)->toDateString();
                    $query->whereDate('date_encountered', $singleDate);
                } catch (\Throwable) {
                    // Ignore date parse errors
                }
            }
        }

        // Order
        $sortBy = $request->input('sort_by', 'id');
        $sortDirection = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'ref_no', 'subject', 'date_encountered', 'duration_to', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('id', 'desc');
        }

        // Statistics counts
        $stats = [
            'total' => (clone $visibleTickets)->count(),
            'pending' => (clone $visibleTickets)->where(function (Builder $ticketStatus) {
                $ticketStatus->whereHas('boardStatus', function (Builder $status) {
                    $status->whereIn('name', ['Inprogress', 'Pending', 'In Progress', 'Open']);
                })->orWhere(function (Builder $legacyStatus) {
                    $legacyStatus->whereNull('board_status_id')->where('status', 1);
                });
            })->count(),
            'closed' => (clone $visibleTickets)->where(function (Builder $ticketStatus) {
                $ticketStatus->whereHas('boardStatus', function (Builder $status) {
                    $status->whereIn('name', ['Closed', 'Resolved', 'Done']);
                })->orWhere('status', 0);
            })->count(),
            'new' => (clone $visibleTickets)->whereHas('boardStatus', function (Builder $status) {
                $status->where('name', 'New');
            })->count(),
        ];

        // Pagination or full list
        $perPage = $request->input('per_page', 10);
        if ($perPage === 'all' || (int) $perPage === -1) {
            $tickets = $query->get();

            return response()->json([
                'data' => $tickets,
                'stats' => $stats,
            ]);
        }

        $paginated = $query->paginate((int) $perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Ticket-focused dashboard metrics. The data uses the same visibility rules
     * as the Tickets List, so non-admin accounts never receive organisation-wide
     * ticket information.
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $query = Ticket::query();
        $this->scopeTicketsVisibleToUser($query, $user);

        $tickets = $query->with([
            'board:id,name',
            'boardStatus.board:id,name',
            'tktType:id,name,code',
            'issueType:issue_id,issue_desc',
            'issueSeverity:id,name,color',
            'creator:id,name,fullname,email',
        ])->latest()->get();

        $closedStatuses = ['closed', 'resolved', 'done', 'cancelled', 'canceled'];
        $newStatuses = ['new'];
        $normaliseStatus = static fn (Ticket $ticket) => strtolower(trim((string) ($ticket->status_name ?: 'Open')));

        $byStatus = $tickets
            ->groupBy(fn (Ticket $ticket) => $ticket->status_name ?: 'Open')
            ->map(fn ($items, $name) => ['name' => $name, 'count' => $items->count()])
            ->sortByDesc('count')
            ->values();

        $byCategory = $tickets
            ->groupBy(fn (Ticket $ticket) => $ticket->board?->name ?: $ticket->boardStatus?->board?->name ?: 'Uncategorised')
            ->map(fn ($items, $name) => ['name' => $name, 'count' => $items->count()])
            ->sortByDesc('count')
            ->values();

        $byIssueType = $tickets
            ->groupBy(fn (Ticket $ticket) => $ticket->tktType?->name ?: $ticket->tktType?->code ?: 'Unspecified')
            ->map(fn ($items, $name) => ['name' => $name, 'count' => $items->count()])
            ->sortByDesc('count')
            ->values();

        $byTicketIssueType = $tickets
            ->groupBy(fn (Ticket $ticket) => $ticket->issueType?->issue_desc ?: 'Unspecified')
            ->map(fn ($items, $name) => ['name' => $name, 'count' => $items->count()])
            ->sortByDesc('count')
            ->values();

        $today = now()->startOfDay();
        $overdue = $tickets->filter(function (Ticket $ticket) use ($closedStatuses, $normaliseStatus, $today) {
            return $ticket->duration_to
                && $ticket->duration_to->lt($today)
                && ! in_array($normaliseStatus($ticket), $closedStatuses, true);
        })->count();

        return response()->json([
            'role' => $user->role,
            'scope' => $user->role === 'admin' ? 'Organisation tickets' : 'Tickets you own, observe, or are assigned to',
            'summary' => [
                'total' => $tickets->count(),
                'new' => $tickets->filter(fn (Ticket $ticket) => in_array($normaliseStatus($ticket), $newStatuses, true))->count(),
                'active' => $tickets->filter(fn (Ticket $ticket) => ! in_array($normaliseStatus($ticket), array_merge($newStatuses, $closedStatuses), true))->count(),
                'closed' => $tickets->filter(fn (Ticket $ticket) => in_array($normaliseStatus($ticket), $closedStatuses, true))->count(),
                'overdue' => $overdue,
            ],
            'by_status' => $byStatus,
            'by_category' => $byCategory,
            'by_issue_type' => $byIssueType,
            'by_ticket_issue_type' => $byTicketIssueType,
            'recent' => $tickets->take(5)->map(function (Ticket $ticket) {
                return [
                    'id' => $ticket->id,
                    'ref_no' => $ticket->ref_no,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status_name ?: 'Open',
                    'status_color' => $ticket->status_color,
                    'priority' => $ticket->severity_name ?: 'Medium',
                    'date_encountered' => optional($ticket->date_encountered)->format('Y-m-d'),
                    'created_at' => optional($ticket->created_at)->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    /**
     * Lookups for dropdowns and filter selects.
     */
    public function lookups(): JsonResponse
    {
        $statuses = BoardStatus::orderBy('order')->get();
        $severities = IssueSeverity::where('enable', 1)->get();
        $types = IssueType::all();
        $users = User::select('id', 'name', 'fullname', 'email', 'image')
            ->with('assignedDepartments:id,user_id,reference,reference_code,default')
            ->get();
        $menus = Menu::select('id', 'name', 'code', 'url')->orderBy('order')->get();

        $user = auth()->user() ?? auth('sanctum')->user();
        $assignedSystems = collect();
        if ($user) {
            $assignedCodes = $user->assignedSystems()->pluck('reference_code')->toArray();
            if (! empty($assignedCodes)) {
                $assignedSystems = System::whereIn('code', $assignedCodes)
                    ->where('status', 'active')
                    ->orderBy('id', 'asc')
                    ->get();
            }
        }

        $boards = Board::select('id', 'name', 'department_id')->where('status', true)->get();
        $departments = Department::select('id', 'code', 'name')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $stations = Station::select('id', 'code', 'name')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $groups = TktGroup::select('id', 'name', 'department_id')
            ->with('members:id,name,fullname,email,image,role')
            ->orderBy('name')
            ->get();
        $tktTypes = TktType::select('id', 'code', 'name', 'board_id')->get();

        return response()->json([
            'statuses' => $statuses,
            'severities' => $severities,
            'types' => $types,
            'users' => $users,
            'menus' => $menus,
            'systems' => $assignedSystems,
            'boards' => $boards,
            'departments' => $departments,
            'stations' => $stations,
            'groups' => $groups,
            'tkt_types' => $tktTypes,
        ]);
    }

    /**
     * Store a newly created ticket into tkt_system_tickets.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'date_encountered' => ['nullable', 'date'],
            'frequency' => ['nullable', 'string', 'max:20'],
            'duration_from' => ['nullable', 'date'],
            'duration_to' => ['nullable', 'date'],
            'url' => ['nullable', 'string', 'max:255'],
            'system' => ['nullable', 'string', 'max:50'],
            'module' => ['nullable', 'string', 'max:255'],
            'board_id' => ['nullable', 'integer', 'exists:tkt_board,id'],
            'issue_group' => ['nullable', 'string', 'max:10'],
            'board_status_id' => ['nullable', 'integer'],
            'issue_type_id' => ['nullable', 'integer'],
            'tkt_type_id' => ['nullable', 'integer'],
            'type_other' => ['nullable', 'string', 'max:255'],
            'issue_severity_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'group_id' => ['nullable', 'integer', 'exists:tkt_groups,id'],
            'workflow_action' => ['nullable', 'string', 'in:reject,confirm_authorize,department_approval,authorization'],
            'assignees' => ['nullable', 'array'],
            'assignees.*' => ['integer', 'exists:users,id'],
            'assigned_groups' => ['nullable', 'array'],
            'assigned_groups.*' => ['integer', 'exists:tkt_groups,id'],
            'followers' => ['nullable', 'array'],
            'followers.*' => ['integer', 'exists:users,id'],
            'follower_groups' => ['nullable', 'array'],
            'follower_groups.*' => ['integer', 'exists:tkt_groups,id'],
            'tagged_groups' => ['nullable', 'array'],
            'tagged_groups.*' => ['integer', 'exists:tkt_groups,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        // Generate unique reference number (e.g. TKT-2026-0001)
        $year = Carbon::now()->year;
        $latestRef = Ticket::withTrashed()
            ->where('ref_no', 'like', "TKT-{$year}-%")
            ->orderByRaw("CAST(SUBSTRING_INDEX(ref_no, '-', -1) AS UNSIGNED) DESC")
            ->value('ref_no');
        $nextNumber = $latestRef
            ? ((int) substr($latestRef, strrpos($latestRef, '-') + 1) + 1)
            : 1;
        do {
            $refNo = sprintf('TKT-%d-%04d', $year, $nextNumber++);
        } while (Ticket::withTrashed()->where('ref_no', $refNo)->exists());

        // Fallback IDs if not provided
        $defaultType = IssueType::first();
        $defaultSeverity = IssueSeverity::first();
        $defaultStatusQuery = BoardStatus::query();
        if (! empty($validated['board_id'])) {
            $defaultStatusQuery->where('board_id', (int) $validated['board_id']);
        } else {
            $defaultStatusQuery->where('name', 'New');
        }
        $defaultStatus = $defaultStatusQuery->orderBy('order')->orderBy('id')->first()
            ?? BoardStatus::where('name', 'New')->first()
            ?? BoardStatus::first();

        $userId = $user->id;
        $defaultEntityIds = $this->defaultAssignedEntityIds($user);

        $ticket = Ticket::create([
            'ref_no' => $refNo,
            'subject' => $validated['subject'],
            'summary' => $validated['summary'],
            'date_encountered' => $validated['date_encountered'] ?? Carbon::now()->toDateString(),
            'frequency' => $validated['frequency'] ?? 'Once',
            'duration_from' => $validated['duration_from'] ?? null,
            'duration_to' => $validated['duration_to'] ?? null,
            'url' => $validated['url'] ?? null,
            'system' => $validated['system'] ?? 'Web',
            'module' => $validated['module'] ?? 'general',
            'issue_group' => $validated['issue_group'] ?? null,
            'board_id' => $validated['board_id'] ?? $defaultStatus?->board_id,
            'board_status_id' => $validated['board_status_id'] ?? $defaultStatus?->id,
            'issue_type_id' => $validated['issue_type_id'] ?? (! empty($validated['tkt_type_id']) ? null : ($defaultType?->issue_id ?? 1)),
            'tkt_type_id' => $validated['tkt_type_id'] ?? null,
            'type_other' => $validated['type_other'] ?? null,
            'issue_severity_id' => $validated['issue_severity_id'] ?? $defaultSeverity?->id,
            'group_id' => $validated['group_id'] ?? ($validated['assigned_groups'][0] ?? null),
            'company_id' => $defaultEntityIds['company_id'],
            'department_id' => $defaultEntityIds['department_id'],
            'status' => 1,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        if (! empty($validated['assignees'])) {
            $ticket->assignees()->sync($validated['assignees']);
        }
        if (array_key_exists('followers', $validated)) {
            $followers = $validated['followers'] ?? [];
            if ((int) $ticket->created_by === (int) $userId) {
                $followers = array_unique(array_merge($followers, [$userId]));
            }
            $ticket->taggedUsers()->sync($followers);
        }
        $hasGroupUpdates = array_key_exists('follower_groups', $validated)
            || array_key_exists('tagged_groups', $validated)
            || array_key_exists('assigned_groups', $validated);
        if ($hasGroupUpdates) {
            $allTaggedGroups = array_values(array_unique(array_filter(array_merge(
                $validated['follower_groups'] ?? [],
                $validated['tagged_groups'] ?? [],
                $validated['assigned_groups'] ?? []
            ), fn ($id) => ! empty($id))));
            $ticket->taggedGroups()->sync($allTaggedGroups);
        }

        $createdTicket = $ticket->fresh()->load('boardStatus');
        $this->writeTicketLog(
            $createdTicket,
            $userId,
            sprintf(
                '%s submitted a new ticket with reference %s',
                $this->userDisplayName($userId),
                $createdTicket->ref_no
            )
        );

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                // Keep ticket uploads grouped by the actual upload date (MMDDYYYY).
                $path = $file->store($this->ticketAttachmentDirectory(), 'public');
                DB::table('tkt_system_ticket_attachments')->insert([
                    'system_ticket_id' => $ticket->id,
                    'user_id' => $userId,
                    'path' => $path,
                    'filename' => $file->getClientOriginalName(),
                    'extension' => $file->getClientOriginalExtension(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        $this->notifyTicketRecipients(
            $ticket,
            $userId,
            sprintf('%s created ticket %s', $this->userDisplayName($userId), $ticket->ref_no),
            'created'
        );

        $ticket->load([
            'boardStatus',
            'issueType',
            'issueSeverity',
            'creator:id,name,fullname,email,image',
            'assignees:id,name,fullname,email,image,role',
            'group:id,name',
            'group.members:id,name,fullname,email,image,role',
            'taggedUsers:id,name,fullname,email,image,role',
            'taggedGroups:id,name',
            'taggedGroups.members:id,name,fullname,email,image,role',
            'attachments',
        ]);

        return response()->json([
            'message' => 'Ticket created successfully',
            'data' => $ticket,
        ], 201)->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Display the specified ticket.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $query = Ticket::with([
            'boardStatus',
            'issueType',
            'tktType',
            'issueSeverity',
            'creator:id,name,fullname,email,image',
            'closer:id,name,fullname,email,image',
            'assignees:id,name,fullname,email,image,role',
            'group:id,name',
            'group.members:id,name,fullname,email,image,role',
            'taggedUsers:id,name,fullname,email,image,role',
            'taggedGroups:id,name',
            'taggedGroups.members:id,name,fullname,email,image,role',
            'attachments',
            'resolutions.attachments',
            'comments.author:id,name,fullname,email,image',
            'comments.attachments',
        ]);
        $this->scopeTicketsVisibleToUser($query, $user);
        $ticket = $query->findOrFail($id);

        $logs = DB::table('tkt_system_ticket_logs as logs')
            ->leftJoin('users as processors', 'processors.id', '=', 'logs.processed_by')
            ->leftJoin('tkt_board_status as log_statuses', 'log_statuses.id', '=', 'logs.board_status_id')
            ->where('logs.system_ticket_id', $ticket->id)
            ->orderByDesc('logs.processed_at')
            ->orderByDesc('logs.id')
            ->select([
                'logs.id',
                'logs.description',
                'logs.is_status_update',
                'logs.processed_by',
                'logs.processed_at',
                'logs.created_at',
                DB::raw("COALESCE(processors.fullname, processors.name, 'System') as processed_by_name"),
                'log_statuses.name as board_status_name',
                'log_statuses.color as board_status_color',
            ])
            ->get();

        $ticket->setAttribute('logs', $logs);

        return response()->json([
            'data' => $ticket,
        ]);
    }

    /**
     * Add a comment or reply to a ticket.
     */
    public function storeComment(Request $request, int $id): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $ticket = $this->findVisibleTicketOrFail($id, $user);
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'integer'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        if (! empty($validated['parent_id'])) {
            $isTicketComment = TicketComment::where('id', $validated['parent_id'])
                ->where('system_ticket_id', $ticket->id)
                ->exists();
            if (! $isTicketComment) {
                return response()->json([
                    'message' => 'The reply target does not belong to this ticket.',
                ], 422);
            }
        }

        $comment = $ticket->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'message' => trim($validated['message']),
            'type' => 'comment',
        ]);
        $comment->load('author:id,name,fullname,email,image');

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('tickets/comments', 'public');
            $comment->attachments()->create([
                'system_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'extension' => $file->getClientOriginalExtension(),
            ]);
        }
        $comment->load('attachments');

        $author = $user->fullname ?: ($user->name ?: $user->email);
        $action = ! empty($validated['parent_id']) ? 'replied to' : 'commented on';
        $this->writeTicketLog(
            $ticket,
            $user->id,
            sprintf('%s %s ticket with reference %s', $author, $action, $ticket->ref_no),
            false
        );
        $this->notifyTicketRecipients(
            $ticket,
            $user->id,
            sprintf('%s %s ticket %s', $author, $action, $ticket->ref_no),
            'comment',
            $comment->message
        );

        return response()->json([
            'message' => 'Comment posted successfully.',
            'data' => $comment,
        ], 201);
    }

    /**
     * Delete a comment created by the authenticated user or an administrator.
     */
    public function deleteComment(Request $request, int $id, int $commentId): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $ticket = $this->findVisibleTicketOrFail($id, $user);
        $comment = $ticket->comments()->with('attachments')->findOrFail($commentId);
        $isAdmin = strtolower((string) $user->role) === 'admin';
        if (! $isAdmin && (int) $comment->user_id !== (int) $user->id) {
            return response()->json(['message' => 'You can only delete your own comments.'], 403);
        }

        foreach ($comment->attachments as $attachment) {
            if ($attachment->path) {
                Storage::disk('public')->delete($attachment->path);
            }
            $attachment->delete();
        }
        $comment->delete();

        $author = $user->fullname ?: ($user->name ?: $user->email);
        $this->writeTicketLog(
            $ticket,
            $user->id,
            sprintf('%s deleted a comment from ticket with reference %s', $author, $ticket->ref_no),
            false
        );

        return response()->json(['message' => 'Comment deleted successfully.']);
    }

    /**
     * Create or update the resolution for a ticket.
     */
    public function saveResolution(Request $request, int $id): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $ticket = $this->findVisibleTicketOrFail($id, $user);
        $validated = $request->validate([
            'resolution' => ['nullable', 'string', 'max:10000'],
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'prevent_recurrence' => ['nullable', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        if (! collect($validated)->filter(fn ($value) => filled($value))->count()) {
            return response()->json(['message' => 'Enter at least one resolution detail.'], 422);
        }

        $resolution = $ticket->resolutions()->create([
            ...$validated,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('tickets/resolutions', 'public');
            $ticket->attachments()->create([
                'resolution_id' => $resolution->id,
                'user_id' => $user->id,
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'extension' => $file->getClientOriginalExtension(),
            ]);
        }

        $author = $user->fullname ?: ($user->name ?: $user->email);
        $this->writeTicketLog(
            $ticket,
            $user->id,
            sprintf('%s added or updated the resolution for ticket with reference %s', $author, $ticket->ref_no),
            false
        );

        return response()->json([
            'message' => 'Ticket resolution saved successfully.',
            'data' => $resolution->load('attachments'),
        ]);
    }

    /**
     * Delete one resolution and its linked files.
     */
    public function deleteResolution(Request $request, int $id, int $resolutionId): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $ticket = $this->findVisibleTicketOrFail($id, $user);
        $resolution = $ticket->resolutions()->with('attachments')->findOrFail($resolutionId);
        $isAdmin = strtolower((string) $user->role) === 'admin';
        if (! $isAdmin && (int) $resolution->created_by !== (int) $user->id) {
            return response()->json(['message' => 'You can only delete your own resolutions.'], 403);
        }

        foreach ($resolution->attachments as $attachment) {
            if ($attachment->path) {
                Storage::disk('public')->delete($attachment->path);
            }
            $attachment->delete();
        }

        $resolution->delete();

        $author = $user->fullname ?: ($user->name ?: $user->email);
        $this->writeTicketLog(
            $ticket,
            $user->id,
            sprintf('%s deleted a resolution from ticket with reference %s', $author, $ticket->ref_no),
            false
        );

        return response()->json(['message' => 'Ticket resolution deleted successfully.']);
    }

    /**
     * Update the specified ticket in tkt_system_tickets.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $ticket = $this->findVisibleTicketOrFail($id, $user);
        $previousStatusId = $ticket->board_status_id;
        $previousDurationFrom = $ticket->getRawOriginal('duration_from');
        $previousDurationTo = $ticket->getRawOriginal('duration_to');
        $previousAssigneeIds = $ticket->assignees()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $validated = $request->validate([
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'summary' => ['sometimes', 'required', 'string'],
            'date_encountered' => ['nullable', 'date'],
            'frequency' => ['nullable', 'string', 'max:20'],
            'duration_from' => ['nullable', 'date'],
            'duration_to' => ['nullable', 'date'],
            'url' => ['nullable', 'string', 'max:255'],
            'system' => ['nullable', 'string', 'max:50'],
            'module' => ['nullable', 'string', 'max:255'],
            'board_id' => ['sometimes', 'nullable', 'integer', 'exists:tkt_board,id'],
            'issue_group' => ['nullable', 'string', 'max:10'],
            'board_status_id' => ['nullable', 'integer'],
            'issue_type_id' => ['nullable', 'integer'],
            'tkt_type_id' => ['nullable', 'integer'],
            'type_other' => ['nullable', 'string', 'max:255'],
            'issue_severity_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'group_id' => ['nullable', 'integer', 'exists:tkt_groups,id'],
            'workflow_action' => ['nullable', 'string', 'in:reject,confirm_authorize,department_approval,authorization'],
            'assignees' => ['nullable', 'array'],
            'assignees.*' => ['integer', 'exists:users,id'],
            'assigned_groups' => ['nullable', 'array'],
            'assigned_groups.*' => ['integer', 'exists:tkt_groups,id'],
            'followers' => ['nullable', 'array'],
            'followers.*' => ['integer', 'exists:users,id'],
            'follower_groups' => ['nullable', 'array'],
            'follower_groups.*' => ['integer', 'exists:tkt_groups,id'],
            'tagged_groups' => ['nullable', 'array'],
            'tagged_groups.*' => ['integer', 'exists:tkt_groups,id'],
        ]);

        $userId = $user->id;
        $validated['updated_by'] = $userId;
        $isAdmin = strtolower((string) $user->role) === 'admin';

        // Confirmation assignees must belong to the selected responsible
        // department. This mirrors the filtered modal and prevents bypassing it.
        if (! empty($validated['workflow_action']) && ! empty($validated['department_id'])) {
            $department = Department::find($validated['department_id']);
            $assigneeIds = $validated['assignees'] ?? [];
            if ($department && ! empty($assigneeIds)) {
                $invalidAssignee = User::whereIn('id', $assigneeIds)
                    ->whereDoesntHave('assignedDepartments', function (Builder $assignments) use ($department) {
                        $assignments->where('reference_code', $department->code);
                    })
                    ->exists();
                if ($invalidAssignee) {
                    return response()->json([
                        'message' => 'Each assignee must be assigned to the selected department.',
                    ], 422);
                }
            }

            if (! empty($validated['group_id'])) {
                $groupMatchesDepartment = TktGroup::whereKey($validated['group_id'])
                    ->where('department_id', $validated['department_id'])
                    ->exists();
                if (! $groupMatchesDepartment) {
                    return response()->json([
                        'message' => 'The selected group must belong to the assigned department.',
                    ], 422);
                }
            }
        }
        $ownerOnlyFields = [
            'subject',
            'summary',
            'date_encountered',
            'frequency',
            'url',
            'system',
            'module',
            'board_id',
            'issue_group',
            'issue_type_id',
            'tkt_type_id',
            'type_other',
            'issue_severity_id',
            'department_id',
            'group_id',
            'assignees',
            'assigned_groups',
            'followers',
            'follower_groups',
            'tagged_groups',
        ];
        $ticketEditRequested = collect($ownerOnlyFields)
            ->contains(fn (string $field) => array_key_exists($field, $validated));
        if ($ticketEditRequested && ! $isAdmin && (int) $ticket->created_by !== (int) $userId) {
            return response()->json([
                'message' => 'Only the ticket owner can edit this ticket.',
            ], 403);
        }
        if ($ticketEditRequested && ! $isAdmin && strtolower((string) $ticket->status_name) !== 'new') {
            return response()->json([
                'message' => 'Only tickets with NEW status can be edited.',
            ], 403);
        }

        $statusChangeRequested = (array_key_exists('board_status_id', $validated)
            && (int) $validated['board_status_id'] !== (int) $previousStatusId)
            || ! empty($validated['workflow_action']);
        $isCurrentAssignee = in_array((int) $userId, $previousAssigneeIds, true);
        $durationUpdateRequested = array_key_exists('duration_from', $validated)
            || array_key_exists('duration_to', $validated);
        if ($durationUpdateRequested && (! ($validated['duration_from'] ?? $ticket->duration_from) || ! ($validated['duration_to'] ?? $ticket->duration_to))) {
            return response()->json([
                'message' => 'Duration From and Duration To are required.',
            ], 422);
        }
        if ($durationUpdateRequested && ! $isAdmin && (int) $ticket->created_by !== (int) $userId && ! $isCurrentAssignee) {
            return response()->json([
                'message' => 'Only the ticket owner or an assigned user can update the ticket duration.',
            ], 403);
        }
        if ($statusChangeRequested && ! $isAdmin && ! $isCurrentAssignee) {
            return response()->json([
                'message' => 'Only an assigned user can change the ticket status.',
            ], 403);
        }

        if (! array_key_exists('board_id', $validated) && isset($validated['board_status_id'])) {
            $validated['board_id'] = BoardStatus::find($validated['board_status_id'])?->board_id;
        }

        if (($validated['workflow_action'] ?? null) === 'reject') {
            $ticketBoardId = $validated['board_id'] ?? $ticket->board_id;
            $cancelledStatus = BoardStatus::where('board_id', $ticketBoardId)
                ->whereRaw('LOWER(name) = ?', ['cancelled'])
                ->first();
            if ($cancelledStatus) {
                $validated['board_status_id'] = $cancelledStatus->id;
            }
        }

        if (($validated['workflow_action'] ?? null) === 'confirm_authorize') {
            $ticketBoardId = $validated['board_id'] ?? $ticket->board_id;
            $assignedStatus = BoardStatus::where('board_id', $ticketBoardId)
                ->whereRaw('LOWER(name) IN (?, ?)', ['assigned', 'accepted'])
                ->orderByRaw("CASE WHEN LOWER(name) = 'assigned' THEN 0 ELSE 1 END")
                ->first();
            if ($assignedStatus) {
                $validated['board_status_id'] = $assignedStatus->id;
            }
        }

        if (! $ticket->company_id || ! $ticket->department_id) {
            $defaultEntityIds = $this->defaultAssignedEntityIds($user);
            if (! $ticket->company_id) {
                $validated['company_id'] = $defaultEntityIds['company_id'];
            }
            if (! $ticket->department_id) {
                $validated['department_id'] = $defaultEntityIds['department_id'];
            }
        }

        // If status changed to closed
        if (isset($validated['board_status_id'])) {
            $statusObj = BoardStatus::find($validated['board_status_id']);
            if ($statusObj && strtolower($statusObj->name) === 'in progress') {
                $durationFrom = $validated['duration_from'] ?? $ticket->duration_from;
                $durationTo = $validated['duration_to'] ?? $ticket->duration_to;
                if (! $durationFrom || ! $durationTo) {
                    return response()->json([
                        'message' => 'Duration From and Duration To are required before moving the ticket to IN PROGRESS.',
                    ], 422);
                }
            }
            if ($statusObj && in_array(strtolower($statusObj->name), ['closed', 'resolved', 'done'], true)) {
                $validated['status'] = 0;
                $validated['closed_by'] = $userId;
                $validated['closed_at'] = Carbon::now();
            } else {
                $validated['status'] = 1;
            }
        }

        $ticket->update($validated);

        if (array_key_exists('assignees', $validated)) {
            $ticket->assignees()->sync($validated['assignees'] ?? []);
        }
        if (array_key_exists('followers', $validated)) {
            $followers = $validated['followers'] ?? [];
            if ((int) $ticket->created_by === (int) $userId) {
                $followers = array_unique(array_merge($followers, [$userId]));
            }
            $ticket->taggedUsers()->sync($followers);
        }
        $hasGroupUpdates = array_key_exists('follower_groups', $validated)
            || array_key_exists('tagged_groups', $validated)
            || array_key_exists('assigned_groups', $validated);

        if ($hasGroupUpdates) {
            $allTaggedGroups = array_values(array_unique(array_filter(array_merge(
                $validated['follower_groups'] ?? [],
                $validated['tagged_groups'] ?? [],
                $validated['assigned_groups'] ?? []
            ), fn ($id) => ! empty($id))));
            $ticket->taggedGroups()->sync($allTaggedGroups);
        }

        if (array_key_exists('assigned_groups', $validated)) {
            $assignedGroup = ! empty($validated['assigned_groups']) ? $validated['assigned_groups'][0] : null;
            $ticket->group_id = $assignedGroup;
            $ticket->save();
        }

        $updatedTicket = $ticket->fresh()->load(['boardStatus', 'assignees:id,name,fullname']);
        $statusChanged = (int) $previousStatusId !== (int) $updatedTicket->board_status_id;
        if ($statusChanged) {
            $this->logTicketStatusChange(
                $updatedTicket,
                $previousStatusId,
                $userId,
                $validated['workflow_action'] ?? null
            );
        }
        $durationChanged = $durationUpdateRequested && (
            $previousDurationFrom !== $updatedTicket->getRawOriginal('duration_from')
            || $previousDurationTo !== $updatedTicket->getRawOriginal('duration_to')
        );
        if ($durationChanged) {
            $formatDurationDate = static fn ($value) => $value
                ? Carbon::parse($value)->format('M j, Y')
                : '—';
            $actor = $this->userDisplayName($userId);
            $this->writeTicketLog(
                $updatedTicket,
                $userId,
                sprintf(
                    '%s updated the ticket duration for %s (Duration From: %s; Duration To: %s)',
                    $actor,
                    $updatedTicket->ref_no,
                    $formatDurationDate($updatedTicket->duration_from),
                    $formatDurationDate($updatedTicket->duration_to)
                ),
                false
            );
        }
        if (($validated['workflow_action'] ?? null) === 'confirm_authorize') {
            $actor = $this->userDisplayName($userId);
            $this->writeTicketLog(
                $updatedTicket,
                $userId,
                sprintf('%s has acknowledged ticket with reference %s', $actor, $updatedTicket->ref_no)
            );
        }
        $currentAssigneeIds = $updatedTicket->assignees->pluck('id')->map(fn ($id) => (int) $id)->all();
        $newAssigneeIds = array_values(array_diff($currentAssigneeIds, $previousAssigneeIds));
        if ($newAssigneeIds) {
            $newAssignees = $updatedTicket->assignees
                ->whereIn('id', $newAssigneeIds);
            $assigneeNames = $newAssignees
                ->map(fn ($assignee) => $assignee->fullname ?: $assignee->name)
                ->implode(', ');
            $actor = $this->userDisplayName($userId);
            $this->writeTicketLog(
                $updatedTicket,
                $userId,
                sprintf('%s has assigned %s to ticket %s', $actor, $assigneeNames, $updatedTicket->ref_no)
            );
            $newAssignees
                ->reject(fn (User $assignee) => (int) $assignee->id === (int) $userId)
                ->each(fn (User $assignee) => $assignee->notify(new TicketActivityNotification(
                    $updatedTicket,
                    sprintf('%s assigned you to ticket %s', $actor, $updatedTicket->ref_no),
                    'assigned'
                )));
        }

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                // Keep ticket uploads grouped by the actual upload date (MMDDYYYY).
                $path = $file->store($this->ticketAttachmentDirectory(), 'public');
                DB::table('tkt_system_ticket_attachments')->insert([
                    'system_ticket_id' => $ticket->id,
                    'user_id' => $userId,
                    'path' => $path,
                    'filename' => $file->getClientOriginalName(),
                    'extension' => $file->getClientOriginalExtension(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        $this->notifyTicketRecipients(
            $ticket,
            $userId,
            sprintf('%s updated ticket %s', $this->userDisplayName($userId), $ticket->ref_no),
            'updated'
        );

        $ticket->load([
            'boardStatus',
            'issueType',
            'issueSeverity',
            'creator:id,name,fullname,email,image',
            'closer:id,name,fullname,email,image',
            'assignees:id,name,fullname,email,image,role',
            'group:id,name',
            'group.members:id,name,fullname,email,image,role',
            'taggedUsers:id,name,fullname,email,image,role',
            'taggedGroups:id,name',
            'taggedGroups.members:id,name,fullname,email,image,role',
            'attachments',
        ]);

        return response()->json([
            'message' => 'Ticket updated successfully',
            'data' => $ticket,
        ])->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Record a workflow status transition in the ticket log.
     */
    private function logTicketStatusChange(
        Ticket $ticket,
        ?int $previousStatusId,
        int $userId,
        ?string $workflowAction = null
    ): void {
        $ticket->loadMissing('boardStatus');
        $currentStatus = $ticket->boardStatus?->name;

        $actor = $this->userDisplayName($userId);
        $this->writeTicketLog(
            $ticket,
            $userId,
            sprintf(
                '%s has updated the status of ticket %s to %s%s',
                $actor,
                $ticket->ref_no,
                $currentStatus ?? (string) $ticket->status,
                $workflowAction ? sprintf(' (Action: %s)', $workflowAction) : ''
            )
        );
    }

    private function userDisplayName(int $userId): string
    {
        $user = User::find($userId);

        return $user?->fullname ?: ($user?->name ?: 'User');
    }

    private function writeTicketLog(Ticket $ticket, int $userId, string $description, bool $isStatusUpdate = true): void
    {
        $ticket->loadMissing('boardStatus');
        $rawStatus = $ticket->getRawOriginal('status');
        $logStatus = is_numeric($rawStatus)
            ? (int) $rawStatus
            : (in_array(strtolower((string) $ticket->boardStatus?->name), ['closed', 'resolved', 'done', 'cancelled'], true) ? 0 : 1);
        $logTimestamp = now();
        try {
            DB::table('tkt_system_ticket_logs')->insert([
                'description' => $description,
                'system_ticket_id' => $ticket->id,
                'board_status_id' => $ticket->board_status_id,
                'group_id' => $ticket->group_id,
                'is_status_update' => $isStatusUpdate ? 1 : 0,
                'processed_by' => $userId,
                'processed_at' => $logTimestamp,
                'ref_no' => $ticket->ref_no,
                'subject' => $ticket->subject,
                'summary' => $ticket->summary,
                'date_encountered' => $ticket->date_encountered?->toDateString() ?? Carbon::now()->toDateString(),
                'frequency' => $ticket->frequency ?? 'Once',
                'duration_from' => $ticket->duration_from?->toDateString(),
                'duration_to' => $ticket->duration_to?->toDateString(),
                'url' => $ticket->url,
                'system' => $ticket->system,
                'module' => $ticket->module,
                'issue_group' => $ticket->issue_group,
                'issue_type_id' => $ticket->issue_type_id,
                'issue_severity_id' => $ticket->issue_severity_id,
                'department_id' => $ticket->department_id,
                'company_id' => $ticket->company_id,
                'status' => $logStatus,
                'created_by' => $ticket->created_by,
                'updated_by' => $userId,
                'created_at' => $logTimestamp,
                'updated_at' => $logTimestamp,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Unable to write ticket status log', [
                'ticket_id' => $ticket->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified ticket.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $ticket = $this->findVisibleTicketOrFail($id, $user);
        $isAdmin = strtolower((string) $user->role) === 'admin';
        if (! $isAdmin && (int) $ticket->created_by !== (int) $user->id) {
            return response()->json(['message' => 'Only the ticket owner can delete this ticket.'], 403);
        }

        $ticket->assignees()->detach();
        $ticket->delete();

        return response()->json([
            'message' => 'Ticket deleted successfully',
        ]);
    }

    /**
     * Resolve the authenticated user's default assigned company and department.
     *
     * @return array{company_id: int|null, department_id: int|null}
     */
    private function defaultAssignedEntityIds(User $user): array
    {
        $defaultCompanyCode = $user->assignments()
            ->where('reference', 'company')
            ->orderByDesc('default')
            ->value('reference_code');
        $defaultDepartmentCode = $user->assignments()
            ->where('reference', 'department')
            ->orderByDesc('default')
            ->value('reference_code');

        return [
            'company_id' => $defaultCompanyCode
                ? Company::where('code', $defaultCompanyCode)->value('id')
                : null,
            'department_id' => $defaultDepartmentCode
                ? Department::where('code', $defaultDepartmentCode)->value('id')
                : null,
        ];
    }

    /**
     * Restrict ticket visibility to tickets the user owns, is assigned to, or
     * observes directly or through a team. Administrators can view all tickets.
     */
    private function scopeTicketsVisibleToUser(Builder $query, ?User $user): void
    {
        if ($user && strtolower((string) $user->role) === 'admin') {
            return;
        }

        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $visibility) use ($user) {
            // The owner always retains visibility, including while the ticket
            // is unassigned. Everyone else can see it only after responsibility
            // has been assigned to a user or a group.
            $visibility->where('created_by', $user->id)
                ->orWhere(function (Builder $assignedTicket) use ($user) {
                    $assignedTicket->where(function (Builder $assignment) {
                        $assignment->whereHas('assignees')
                            ->orWhereNotNull('group_id');
                    })->where(function (Builder $participant) use ($user) {
                        $participant->whereHas('assignees', function (Builder $assignees) use ($user) {
                            $assignees->where('users.id', $user->id);
                        })->orWhereHas('taggedUsers', function (Builder $observers) use ($user) {
                            $observers->where('users.id', $user->id);
                        })->orWhereIn('group_id', function ($groups) use ($user) {
                            $groups->select('group_id')
                                ->from('tkt_group_user')
                                ->where('user_id', $user->id);
                        })->orWhereHas('taggedGroups', function (Builder $observerGroups) use ($user) {
                            $observerGroups->whereHas('members', function (Builder $members) use ($user) {
                                $members->where('users.id', $user->id);
                            });
                        });
                    });
                });
        });
    }

    /**
     * Return a ticket only when it is visible to the authenticated user.
     */
    private function findVisibleTicketOrFail(int $id, ?User $user): Ticket
    {
        $query = Ticket::query();
        $this->scopeTicketsVisibleToUser($query, $user);

        return $query->findOrFail($id);
    }

    /**
     * Resolve the Sanctum user for ticket mutations without route middleware.
     */
    private function authenticatedUser(Request $request): ?User
    {
        return $request->user('sanctum') ?? auth('sanctum')->user() ?? $request->attributes->get('integration_user');
    }

    /** Broadcast a persisted notification to assignees, observers, assigned groups, and @mentions. */
    private function notifyTicketRecipients(Ticket $ticket, int $actorId, string $message, string $kind, ?string $mentionText = null): void
    {
        $ticket->loadMissing([
            'creator:id,name,fullname,email,username',
            'assignees:id,name,fullname,email,username',
            'taggedUsers:id,name,fullname,email,username',
            'group.members:id,name,fullname,email,username',
            'taggedGroups.members:id,name,fullname,email,username',
        ]);

        $recipients = collect()
            ->merge($ticket->assignees)
            ->merge($ticket->taggedUsers)
            ->merge($ticket->group?->members ?? collect())
            ->merge($ticket->taggedGroups->flatMap->members);

        // A ticket owner is notified about discussion activity even when they
        // are not also assigned or observing the ticket.
        if ($kind === 'comment' && $ticket->creator) {
            $recipients->push($ticket->creator);
        }

        if ($mentionText) {
            $mentioned = User::query()->get()->filter(function (User $candidate) use ($mentionText) {
                return collect([$candidate->username, $candidate->name, $candidate->fullname])
                    ->filter()
                    ->contains(fn ($value) => str_contains(mb_strtolower($mentionText), '@'.mb_strtolower($value)));
            });
            $recipients = $recipients->merge($mentioned);
        }

        $recipients->unique('id')->reject(fn (User $recipient) => $recipient->id === $actorId)
            ->each(fn (User $recipient) => $recipient->notify(new TicketActivityNotification($ticket, $message, $kind)));
    }

    /**
     * Ticket attachments are stored under the calendar date on which they are uploaded.
     */
    private function ticketAttachmentDirectory(): string
    {
        return 'tickets/attachments/'.Carbon::now(config('app.timezone'))->format('mdY');
    }

    /**
     * Bulk delete tickets.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ids = $validated['ids'];
        $tickets = Ticket::whereIn('id', $ids)->get(['id', 'created_by']);
        $isAdmin = strtolower((string) $user->role) === 'admin';

        if (! $isAdmin && $tickets->contains(fn ($ticket) => (int) $ticket->created_by !== (int) $user->id)) {
            return response()->json(['message' => 'You can only delete tickets that you created.'], 403);
        }

        DB::table('tkt_system_ticket_assignee')
            ->whereIn('system_ticket_id', $ids)
            ->delete();

        Ticket::whereIn('id', $ids)->delete();

        return response()->json([
            'message' => count($ids).' tickets deleted successfully',
        ]);
    }

    /**
     * Delete a single ticket attachment.
     */
    public function destroyAttachment(int $ticketId, int $attachmentId): JsonResponse
    {
        $attachment = DB::table('tkt_system_ticket_attachments')
            ->where('system_ticket_id', $ticketId)
            ->where('id', $attachmentId)
            ->first();

        if (! $attachment) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        if (! empty($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }

        DB::table('tkt_system_ticket_attachments')
            ->where('id', $attachmentId)
            ->delete();

        return response()->json(['message' => 'Attachment deleted successfully']);
    }
}
