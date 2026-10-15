<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IssueType;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IssueTypeController extends Controller
{
    /**
     * Display a listing of issue types from tkt_issue_types.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $query = IssueType::query()->with(['board:id,name'])->withCount('tickets');

        if (! empty($search)) {
            $query->where('issue_desc', 'like', "%{$search}%");
        }

        $types = $query->orderBy('issue_id', 'asc')->get();

        $mostCommon = IssueType::withCount('tickets')
            ->orderByDesc('tickets_count')
            ->first();

        $stats = [
            'total_types' => IssueType::count(),
            'total_tickets' => Ticket::whereNotNull('issue_type_id')->count(),
            'active_types' => IssueType::has('tickets')->count(),
            'most_common' => $mostCommon && $mostCommon->tickets_count > 0 ? $mostCommon->issue_desc : 'None',
        ];

        return response()->json([
            'data' => $types,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created issue type in tkt_issue_types.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'issue_desc' => ['required', 'string', 'max:50', 'unique:tkt_issue_types,issue_desc'],
            'color' => ['nullable', 'string', 'max:255'],
            'board_id' => ['nullable', 'integer', 'exists:tkt_board,id'],
        ]);

        $issueType = IssueType::create([
            'issue_desc' => $validated['issue_desc'],
            'color' => $validated['color'] ?? '#405189',
            'board_id' => $validated['board_id'] ?? null,
        ]);

        $issueType->load(['board:id,name'])->loadCount('tickets');

        return response()->json([
            'message' => 'Issue type created successfully',
            'data' => $issueType,
        ], 201);
    }

    /**
     * Display the specified issue type.
     */
    public function show(int $id): JsonResponse
    {
        $issueType = IssueType::with(['board:id,name'])->withCount('tickets')->find($id);

        if (! $issueType) {
            return response()->json([
                'message' => 'Issue type not found',
            ], 404);
        }

        return response()->json([
            'data' => $issueType,
        ]);
    }

    /**
     * Update the specified issue type in tkt_issue_types.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $issueType = IssueType::find($id);

        if (! $issueType) {
            return response()->json([
                'message' => 'Issue type not found',
            ], 404);
        }

        $validated = $request->validate([
            'issue_desc' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tkt_issue_types', 'issue_desc')->ignore($issueType->issue_id, 'issue_id'),
            ],
            'color' => ['nullable', 'string', 'max:255'],
            'board_id' => ['nullable', 'integer', 'exists:tkt_board,id'],
        ]);

        $issueType->update([
            'issue_desc' => $validated['issue_desc'],
            'color' => $validated['color'] ?? $issueType->color ?? '#405189',
            'board_id' => $validated['board_id'] ?? null,
        ]);

        $issueType->load(['board:id,name'])->loadCount('tickets');

        return response()->json([
            'message' => 'Issue type updated successfully',
            'data' => $issueType,
        ]);
    }

    /**
     * Remove the specified issue type from tkt_issue_types.
     */
    public function destroy(int $id): JsonResponse
    {
        $issueType = IssueType::find($id);

        if (! $issueType) {
            return response()->json([
                'message' => 'Issue type not found',
            ], 404);
        }

        if ($issueType->tickets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this issue type because it is currently assigned to existing tickets.',
            ], 422);
        }

        $issueType->delete();

        return response()->json([
            'message' => 'Issue type deleted successfully',
        ]);
    }
}
