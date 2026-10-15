<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\System;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemController extends Controller
{
    /**
     * Display a listing of systems.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = System::query();

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        $systems = $query->orderBy('id', 'asc')->get();

        $stats = [
            'total' => System::count(),
            'active' => System::where('status', 'active')->count(),
            'inactive' => System::where('status', 'inactive')->count(),
        ];

        return response()->json([
            'data' => $systems,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created system.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:systems,code'],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $system = System::create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'System created successfully',
            'data' => $system,
        ], 201);
    }

    /**
     * Display the specified system.
     */
    public function show(int $id): JsonResponse
    {
        $system = System::find($id);

        if (! $system) {
            return response()->json([
                'message' => 'System not found',
            ], 404);
        }

        return response()->json([
            'data' => $system,
        ]);
    }

    /**
     * Update the specified system.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $system = System::find($id);

        if (! $system) {
            return response()->json([
                'message' => 'System not found',
            ], 404);
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('systems', 'code')->ignore($system->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $system->update([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'status' => $validated['status'] ?? $system->status ?? 'active',
        ]);

        return response()->json([
            'message' => 'System updated successfully',
            'data' => $system->fresh(),
        ]);
    }

    /**
     * Remove the specified system.
     */
    public function destroy(int $id): JsonResponse
    {
        $system = System::find($id);

        if (! $system) {
            return response()->json([
                'message' => 'System not found',
            ], 404);
        }

        if ($system->tickets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete system because it is associated with existing tickets.',
            ], 422);
        }

        $system->delete();

        return response()->json([
            'message' => 'System deleted successfully',
        ]);
    }
}
