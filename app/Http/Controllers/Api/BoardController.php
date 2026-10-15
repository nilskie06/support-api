<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\BoardStatus;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoardController extends Controller
{
    /**
     * Display a listing of boards from tkt_board.
     */
    public function index(Request $request): JsonResponse
    {
        $boards = Board::query()
            ->with(['statuses', 'department:id,code,name'])
            ->withCount(['statuses'])
            ->orderBy('id', 'asc')
            ->get();

        // Calculate statistics
        $stats = [
            'total_boards' => Board::count(),
            'active_boards' => Board::where('status', true)->count(),
            'total_statuses' => BoardStatus::count(),
            'total_tickets' => Ticket::count(),
        ];

        return response()->json([
            'data' => $boards,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created board in tkt_board.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'statuses' => ['nullable', 'array'],
            'statuses.*.name' => ['required_with:statuses', 'string', 'max:255'],
            'statuses.*.color' => ['nullable', 'string', 'max:255'],
            'statuses.*.type' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = auth()->id() ?? 1;

        $board = Board::create([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? true,
            'department_id' => $validated['department_id'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        // If custom statuses were supplied, create them
        if (! empty($validated['statuses'])) {
            foreach ($validated['statuses'] as $index => $statusData) {
                BoardStatus::create([
                    'board_id' => $board->id,
                    'name' => $statusData['name'],
                    'color' => $statusData['color'] ?? 'primary',
                    'type' => $statusData['type'] ?? 'open',
                    'order' => $index + 1,
                    'is_required' => false,
                    'has_next_step' => true,
                    'notify_creator' => false,
                    'notify_followers' => false,
                    'notify_assignees' => false,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        } else {
            // Default 4-step workflow
            $defaultSteps = [
                ['name' => 'New', 'color' => 'info', 'type' => 'open'],
                ['name' => 'Open', 'color' => 'primary', 'type' => 'open'],
                ['name' => 'In Progress', 'color' => 'warning', 'type' => 'progress'],
                ['name' => 'Closed', 'color' => 'danger', 'type' => 'closed'],
            ];

            foreach ($defaultSteps as $index => $step) {
                BoardStatus::create([
                    'board_id' => $board->id,
                    'name' => $step['name'],
                    'color' => $step['color'],
                    'type' => $step['type'],
                    'order' => $index + 1,
                    'is_required' => false,
                    'has_next_step' => true,
                    'notify_creator' => false,
                    'notify_followers' => false,
                    'notify_assignees' => false,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        }

        $board->load(['statuses', 'department:id,code,name']);

        return response()->json([
            'message' => 'Board created successfully',
            'data' => $board,
        ], 201);
    }

    /**
     * Display the specified board by ID or name.
     */
    public function show(string|int $id): JsonResponse
    {
        $query = Board::with(['department:id,code,name', 'statuses' => function ($query) {
            $query->orderBy('order', 'asc');
        }]);

        if (is_numeric($id)) {
            $board = $query->findOrFail((int) $id);
        } else {
            $board = $query->where('name', 'like', $id)
                ->orWhere('name', 'like', "%{$id}%")
                ->firstOrFail();
        }

        return response()->json([
            'data' => $board,
        ]);
    }

    /**
     * Update the specified board in tkt_board.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $board = Board::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $userId = auth()->id() ?? 1;

        $board->update([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? $board->status,
            'department_id' => $validated['department_id'] ?? null,
            'updated_by' => $userId,
        ]);

        $board->load(['statuses', 'department:id,code,name']);

        return response()->json([
            'message' => 'Board updated successfully',
            'data' => $board,
        ]);
    }

    /**
     * Remove the specified board from tkt_board.
     */
    public function destroy(int $id): JsonResponse
    {
        $board = Board::findOrFail($id);

        // Check if tickets belong to this board's statuses
        $statusIds = $board->statuses()->pluck('id');
        $hasTickets = Ticket::whereIn('board_status_id', $statusIds)->exists();

        if ($hasTickets) {
            return response()->json([
                'message' => 'Cannot delete board because tickets are currently assigned to its statuses.',
            ], 422);
        }

        // Soft delete statuses and board
        BoardStatus::where('board_id', $board->id)->delete();
        $board->delete();

        return response()->json([
            'message' => 'Board and its workflow statuses deleted successfully',
        ]);
    }

    /**
     * Add a status to a specific board.
     */
    public function storeStatus(Request $request, int $boardId): JsonResponse
    {
        $board = Board::findOrFail($boardId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'has_next_step' => ['nullable', 'boolean'],
            'notify_creator' => ['nullable', 'boolean'],
            'notify_followers' => ['nullable', 'boolean'],
            'notify_assignees' => ['nullable', 'boolean'],
        ]);

        $userId = auth()->id() ?? 1;
        $maxOrder = BoardStatus::where('board_id', $boardId)->max('order') ?? 0;

        $status = BoardStatus::create([
            'board_id' => $board->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? 'primary',
            'type' => $validated['type'] ?? 'open',
            'order' => $maxOrder + 1,
            'is_required' => $validated['is_required'] ?? false,
            'has_next_step' => $validated['has_next_step'] ?? true,
            'notify_creator' => $validated['notify_creator'] ?? false,
            'notify_followers' => $validated['notify_followers'] ?? false,
            'notify_assignees' => $validated['notify_assignees'] ?? false,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return response()->json([
            'message' => 'Status added successfully',
            'data' => $status,
        ], 201);
    }

    /**
     * Update an individual status on a board.
     */
    public function updateStatus(Request $request, int $boardId, int $statusId): JsonResponse
    {
        $status = BoardStatus::where('board_id', $boardId)->findOrFail($statusId);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'integer'],
            'is_required' => ['nullable', 'boolean'],
            'has_next_step' => ['nullable', 'boolean'],
            'notify_creator' => ['nullable', 'boolean'],
            'notify_followers' => ['nullable', 'boolean'],
            'notify_assignees' => ['nullable', 'boolean'],
        ]);

        $userId = auth()->id() ?? 1;
        $validated['updated_by'] = $userId;

        $status->update($validated);

        return response()->json([
            'message' => 'Status updated successfully',
            'data' => $status,
        ]);
    }

    /**
     * Delete a status from a board.
     */
    public function destroyStatus(int $boardId, int $statusId): JsonResponse
    {
        $status = BoardStatus::where('board_id', $boardId)->findOrFail($statusId);

        // Check if tickets are using this status
        if (Ticket::where('board_status_id', $statusId)->exists()) {
            return response()->json([
                'message' => 'Cannot delete this status because active tickets are linked to it.',
            ], 422);
        }

        $status->delete();

        return response()->json([
            'message' => 'Status deleted successfully',
        ]);
    }

    /**
     * Reorder statuses for a board.
     */
    public function reorderStatuses(Request $request, int $boardId): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer'],
            'orders.*.order' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($validated, $boardId) {
            foreach ($validated['orders'] as $item) {
                BoardStatus::where('board_id', $boardId)
                    ->where('id', $item['id'])
                    ->update(['order' => $item['order']]);
            }
        });

        $updatedStatuses = BoardStatus::where('board_id', $boardId)
            ->orderBy('order', 'asc')
            ->get();

        return response()->json([
            'message' => 'Statuses reordered successfully',
            'data' => $updatedStatuses,
        ]);
    }
}
