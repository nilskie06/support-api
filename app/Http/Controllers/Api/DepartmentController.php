<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    /**
     * Display a listing of departments.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Department::query();

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        $departments = $query->orderBy('id', 'asc')->get();

        $stats = [
            'total' => Department::count(),
            'active' => Department::where('status', 'active')->count(),
            'inactive' => Department::where('status', 'inactive')->count(),
        ];

        return response()->json([
            'data' => $departments,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $department = Department::create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Department created successfully',
            'data' => $department,
        ], 201);
    }

    /**
     * Display the specified department.
     */
    public function show(int $id): JsonResponse
    {
        $department = Department::find($id);

        if (! $department) {
            return response()->json([
                'message' => 'Department not found',
            ], 404);
        }

        return response()->json([
            'data' => $department,
        ]);
    }

    /**
     * Update the specified department.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $department = Department::find($id);

        if (! $department) {
            return response()->json([
                'message' => 'Department not found',
            ], 404);
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->ignore($department->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $department->update([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'status' => $validated['status'] ?? $department->status ?? 'active',
        ]);

        return response()->json([
            'message' => 'Department updated successfully',
            'data' => $department->fresh(),
        ]);
    }

    /**
     * Remove the specified department.
     */
    public function destroy(int $id): JsonResponse
    {
        $department = Department::find($id);

        if (! $department) {
            return response()->json([
                'message' => 'Department not found',
            ], 404);
        }

        if ($department->tickets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete department because it is associated with existing tickets.',
            ], 422);
        }

        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully',
        ]);
    }
}
