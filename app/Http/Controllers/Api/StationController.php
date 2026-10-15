<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StationController extends Controller
{
    /**
     * Display a listing of stations.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Station::query();

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        $stations = $query->orderBy('id', 'asc')->get();

        $stats = [
            'total' => Station::count(),
            'active' => Station::where('status', 'active')->count(),
            'inactive' => Station::where('status', 'inactive')->count(),
        ];

        return response()->json([
            'data' => $stations,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created station.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:stations,code'],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $station = Station::create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Station created successfully',
            'data' => $station,
        ], 201);
    }

    /**
     * Display the specified station.
     */
    public function show(int $id): JsonResponse
    {
        $station = Station::find($id);

        if (! $station) {
            return response()->json([
                'message' => 'Station not found',
            ], 404);
        }

        return response()->json([
            'data' => $station,
        ]);
    }

    /**
     * Update the specified station.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $station = Station::find($id);

        if (! $station) {
            return response()->json([
                'message' => 'Station not found',
            ], 404);
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('stations', 'code')->ignore($station->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $station->update([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'status' => $validated['status'] ?? $station->status ?? 'active',
        ]);

        return response()->json([
            'message' => 'Station updated successfully',
            'data' => $station->fresh(),
        ]);
    }

    /**
     * Remove the specified station.
     */
    public function destroy(int $id): JsonResponse
    {
        $station = Station::find($id);

        if (! $station) {
            return response()->json([
                'message' => 'Station not found',
            ], 404);
        }

        $station->delete();

        return response()->json([
            'message' => 'Station deleted successfully',
        ]);
    }
}
