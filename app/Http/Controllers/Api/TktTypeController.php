<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\System;
use App\Models\Ticket;
use App\Models\TktType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TktTypeController extends Controller
{
    /**
     * Display a listing of ticket types from tkt_type.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $boardId = $request->query('board_id');

        $query = TktType::query()->with('board:id,name')->withCount('tickets');

        if (! empty($boardId)) {
            $query->where('board_id', $boardId);
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $types = $query->orderBy('id', 'asc')->get();

        $stats = [
            'total_types' => TktType::count(),
            'total_tickets' => Ticket::whereNotNull('tkt_type_id')->count(),
            'facilities_types' => TktType::where('board_id', 3)->count(),
            'boards_count' => Board::where('status', true)->count(),
        ];

        return response()->json([
            'data' => $types,
            'stats' => $stats,
        ]);
    }

    /**
     * Bulk register systems into tkt_type for the System board.
     */
    public function registerSystems(Request $request): JsonResponse
    {
        $systemBoard = Board::where('name', 'System')->first();
        $boardId = $request->input('board_id') ?? ($systemBoard ? $systemBoard->id : 1);

        $query = System::query()->where('status', 'active');

        if ($request->has('system_ids') && is_array($request->input('system_ids')) && ! empty($request->input('system_ids'))) {
            $query->whereIn('id', $request->input('system_ids'));
        }

        $systems = $query->get();
        $registered = [];

        foreach ($systems as $sys) {
            $registered[] = TktType::updateOrCreate(
                [
                    'code' => $sys->code,
                    'board_id' => $boardId,
                ],
                [
                    'name' => $sys->name,
                ]
            );
        }

        return response()->json([
            'message' => count($registered).' system(s) registered in ticket types successfully',
            'data' => $registered,
            'count' => count($registered),
        ]);
    }

    /**
     * Store a newly created ticket type in tkt_type.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->boolean('all_systems') || $request->has('system_ids')) {
            return $this->registerSystems($request);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'board_id' => ['nullable', 'integer', 'exists:tkt_board,id'],
        ]);

        $tktType = TktType::create($validated);
        $tktType->load('board:id,name');

        return response()->json([
            'message' => 'Ticket type created successfully',
            'data' => $tktType,
        ], 201);
    }

    /**
     * Display the specified ticket type.
     */
    public function show(int $id): JsonResponse
    {
        $tktType = TktType::with('board:id,name')->withCount('tickets')->findOrFail($id);

        return response()->json([
            'data' => $tktType,
        ]);
    }

    /**
     * Update the specified ticket type in tkt_type.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tktType = TktType::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'board_id' => ['nullable', 'integer', 'exists:tkt_board,id'],
        ]);

        $tktType->update($validated);
        $tktType->load('board:id,name');

        return response()->json([
            'message' => 'Ticket type updated successfully',
            'data' => $tktType,
        ]);
    }

    /**
     * Remove the specified ticket type from tkt_type.
     */
    public function destroy(int $id): JsonResponse
    {
        $tktType = TktType::find($id);

        if (! $tktType) {
            return response()->json([
                'message' => 'Ticket type not found',
            ], 404);
        }

        if ($tktType->tickets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this type because it is currently assigned to existing tickets.',
            ], 422);
        }

        $tktType->delete();

        return response()->json([
            'message' => 'Ticket type deleted successfully',
        ]);
    }
}
