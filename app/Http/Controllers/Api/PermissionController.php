<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleHasPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions with usage statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Permission::query();

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('guard_name', 'like', "%{$search}%");
            });
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        $permissions = $query->orderBy('permission_id', 'asc')->get();

        $formattedPermissions = $permissions->map(function (Permission $perm) {
            $rolesCount = RoleHasPermission::where('permission_id', $perm->permission_id)
                ->distinct('role_id')
                ->count('role_id');
            $modulesCount = RoleHasPermission::where('permission_id', $perm->permission_id)
                ->distinct('menu_id')
                ->count('menu_id');

            return [
                'permission_id' => $perm->permission_id,
                'id' => $perm->permission_id,
                'name' => $perm->name,
                'guard_name' => $perm->guard_name,
                'description' => $perm->description,
                'status' => $perm->status,
                'created_at' => $perm->created_at ? $perm->created_at->format('M d, Y') : null,
                'raw_created_at' => $perm->created_at,
                'stats' => [
                    'roles_count' => $rolesCount,
                    'modules_count' => $modulesCount,
                ],
            ];
        });

        $stats = [
            'total' => Permission::count(),
            'active' => Permission::where('status', 'active')->count(),
            'inactive' => Permission::where('status', 'inactive')->count(),
            'total_roles' => Role::count(),
        ];

        return response()->json([
            'data' => $formattedPermissions,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:permissions,name'],
            'guard_name' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $permission = Permission::create([
            'name' => strtolower(trim($validated['name'])),
            'guard_name' => ! empty($validated['guard_name']) ? trim($validated['guard_name']) : 'web',
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Permission created successfully',
            'data' => $permission,
        ], 201);
    }

    /**
     * Display the specified permission.
     */
    public function show(int $permission_id): JsonResponse
    {
        $permission = Permission::find($permission_id);

        if (! $permission) {
            return response()->json([
                'message' => 'Permission not found',
            ], 404);
        }

        $rolesCount = RoleHasPermission::where('permission_id', $permission->permission_id)
            ->distinct('role_id')
            ->count('role_id');
        $modulesCount = RoleHasPermission::where('permission_id', $permission->permission_id)
            ->distinct('menu_id')
            ->count('menu_id');

        return response()->json([
            'data' => [
                'permission_id' => $permission->permission_id,
                'id' => $permission->permission_id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'description' => $permission->description,
                'status' => $permission->status,
                'created_at' => $permission->created_at ? $permission->created_at->format('M d, Y') : null,
                'stats' => [
                    'roles_count' => $rolesCount,
                    'modules_count' => $modulesCount,
                ],
            ],
        ]);
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, int $permission_id): JsonResponse
    {
        $permission = Permission::find($permission_id);

        if (! $permission) {
            return response()->json([
                'message' => 'Permission not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('permissions', 'name')->ignore($permission->permission_id, 'permission_id'),
            ],
            'guard_name' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $permission->update([
            'name' => strtolower(trim($validated['name'])),
            'guard_name' => ! empty($validated['guard_name']) ? trim($validated['guard_name']) : $permission->guard_name,
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'status' => $validated['status'] ?? $permission->status ?? 'active',
        ]);

        return response()->json([
            'message' => 'Permission updated successfully',
            'data' => $permission->fresh(),
        ]);
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(int $permission_id): JsonResponse
    {
        $permission = Permission::find($permission_id);

        if (! $permission) {
            return response()->json([
                'message' => 'Permission not found',
            ], 404);
        }

        RoleHasPermission::where('permission_id', $permission->permission_id)->delete();
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully',
        ]);
    }
}
