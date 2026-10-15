<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IssueSeverity;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IssueSeverityController extends Controller
{
    /**
     * Display a listing of severities from tkt_issue_severities.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $query = IssueSeverity::query()->withCount('tickets');

        if (! empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        $severities = $query->orderBy('id', 'asc')->get();

        $stats = [
            'total_severities' => IssueSeverity::count(),
            'total_tickets' => Ticket::whereNotNull('issue_severity_id')->count(),
            'active_severities' => IssueSeverity::where('enable', 1)->count(),
            'disabled_severities' => IssueSeverity::where('enable', 0)->count(),
        ];

        return response()->json([
            'data' => $severities,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created severity in tkt_issue_severities.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:tkt_issue_severities,name'],
            'color' => ['nullable', 'string', 'max:50'],
            'enable' => ['nullable', 'boolean'],
        ]);

        $severity = IssueSeverity::create([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? 'primary',
            'enable' => $validated['enable'] ?? true,
        ]);

        return response()->json([
            'message' => 'Severity created successfully',
            'data' => $severity,
        ], 201);
    }

    /**
     * Display the specified severity.
     */
    public function show(int $id): JsonResponse
    {
        $severity = IssueSeverity::withCount('tickets')->findOrFail($id);

        return response()->json([
            'data' => $severity,
        ]);
    }

    /**
     * Update the specified severity in tkt_issue_severities.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $severity = IssueSeverity::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('tkt_issue_severities', 'name')->ignore($severity->id)],
            'color' => ['nullable', 'string', 'max:50'],
            'enable' => ['nullable', 'boolean'],
        ]);

        $severity->update($validated);

        return response()->json([
            'message' => 'Severity updated successfully',
            'data' => $severity,
        ]);
    }

    /**
     * Remove the specified severity from tkt_issue_severities.
     */
    public function destroy(int $id): JsonResponse
    {
        $severity = IssueSeverity::find($id);

        if (! $severity) {
            return response()->json([
                'message' => 'Severity not found',
            ], 404);
        }

        if ($severity->tickets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this severity because it is currently assigned to existing tickets.',
            ], 422);
        }

        $severity->delete();

        return response()->json([
            'message' => 'Severity deleted successfully',
        ]);
    }
}
