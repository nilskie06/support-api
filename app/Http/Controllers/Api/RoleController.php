<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleHasPermission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Display a listing of roles with usage statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Role::query();

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

        $roles = $query->orderBy('role_id', 'asc')->get();

        $totalMenus = Menu::count();

        $formattedRoles = $roles->map(function (Role $role) use ($totalMenus) {
            $permissionsCount = RoleHasPermission::where('role_id', $role->role_id)->count();
            $accessibleModulesCount = RoleHasPermission::where('role_id', $role->role_id)
                ->distinct('menu_id')
                ->count('menu_id');
            $usersCount = User::where('role', $role->name)->count();

            return [
                'role_id' => $role->role_id,
                'id' => $role->role_id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'description' => $role->description,
                'status' => $role->status,
                'created_at' => $role->created_at ? $role->created_at->format('M d, Y') : null,
                'raw_created_at' => $role->created_at,
                'stats' => [
                    'permissions_count' => $permissionsCount,
                    'accessible_modules_count' => $accessibleModulesCount,
                    'total_modules_count' => $totalMenus,
                    'users_count' => $usersCount,
                ],
            ];
        });

        $stats = [
            'total' => Role::count(),
            'active' => Role::where('status', 'active')->count(),
            'inactive' => Role::where('status', 'inactive')->count(),
            'total_permissions' => Permission::count(),
            'total_modules' => $totalMenus,
        ];

        return response()->json([
            'data' => $formattedRoles,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'guard_name' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'copy_from_role_id' => ['nullable', 'integer', 'exists:roles,role_id'],
        ]);

        $role = Role::create([
            'name' => strtolower(trim($validated['name'])),
            'guard_name' => ! empty($validated['guard_name']) ? trim($validated['guard_name']) : 'web',
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'status' => $validated['status'] ?? 'active',
        ]);

        // Copy permissions if specified
        if (! empty($validated['copy_from_role_id'])) {
            $sourcePermissions = RoleHasPermission::where('role_id', $validated['copy_from_role_id'])->get();
            foreach ($sourcePermissions as $src) {
                RoleHasPermission::create([
                    'role_id' => $role->role_id,
                    'permission_id' => $src->permission_id,
                    'menu_id' => $src->menu_id,
                ]);
            }
        }

        return response()->json([
            'message' => 'Role created successfully',
            'data' => $role,
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(int $role_id): JsonResponse
    {
        $role = Role::find($role_id);

        if (! $role) {
            return response()->json([
                'message' => 'Role not found',
            ], 404);
        }

        $permissionsCount = RoleHasPermission::where('role_id', $role->role_id)->count();
        $accessibleModulesCount = RoleHasPermission::where('role_id', $role->role_id)
            ->distinct('menu_id')
            ->count('menu_id');
        $usersCount = User::where('role', $role->name)->count();

        return response()->json([
            'data' => [
                'role_id' => $role->role_id,
                'id' => $role->role_id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'description' => $role->description,
                'status' => $role->status,
                'created_at' => $role->created_at ? $role->created_at->format('M d, Y') : null,
                'raw_created_at' => $role->created_at,
                'stats' => [
                    'permissions_count' => $permissionsCount,
                    'accessible_modules_count' => $accessibleModulesCount,
                    'total_modules_count' => Menu::count(),
                    'users_count' => $usersCount,
                ],
            ],
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, int $role_id): JsonResponse
    {
        $role = Role::find($role_id);

        if (! $role) {
            return response()->json([
                'message' => 'Role not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore($role->role_id, 'role_id'),
            ],
            'guard_name' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $oldName = $role->name;
        $newName = strtolower(trim($validated['name']));

        $role->update([
            'name' => $newName,
            'guard_name' => ! empty($validated['guard_name']) ? trim($validated['guard_name']) : $role->guard_name,
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'status' => $validated['status'] ?? $role->status ?? 'active',
        ]);

        // If role name changed, update users with old role name
        if ($oldName !== $newName) {
            User::where('role', $oldName)->update(['role' => $newName]);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'data' => $role->fresh(),
        ]);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(int $role_id): JsonResponse
    {
        $role = Role::find($role_id);

        if (! $role) {
            return response()->json([
                'message' => 'Role not found',
            ], 404);
        }

        if (strtolower($role->name) === 'admin') {
            return response()->json([
                'message' => 'Cannot delete the primary Admin role.',
            ], 403);
        }

        $usersCount = User::where('role', $role->name)->count();
        if ($usersCount > 0) {
            return response()->json([
                'message' => "Cannot delete role because {$usersCount} user(s) are currently assigned to it.",
            ], 422);
        }

        RoleHasPermission::where('role_id', $role->role_id)->delete();
        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Get the full module permissions matrix for a role (Matching Screenshot).
     */
    public function getMatrix(int $role_id): JsonResponse
    {
        $role = Role::find($role_id);

        if (! $role) {
            return response()->json([
                'message' => 'Role not found',
            ], 404);
        }

        $allPermissions = Permission::where('status', 'active')
            ->orderBy('permission_id')
            ->get(['permission_id', 'name', 'description']);

        $allMenus = Menu::orderBy('order')->get();
        $menuTree = Menu::root()->with('allChildren')->get();

        $rolePermissions = RoleHasPermission::where('role_id', $role->role_id)
            ->get();

        // Build matrix mapping: matrix[menu_id][permission_name] = true
        $permissionNameMap = $allPermissions->keyBy('permission_id');
        $matrix = [];
        $assignedPermissionsCount = 0;
        $accessibleMenuIds = [];

        foreach ($rolePermissions as $rp) {
            $menuId = (int) $rp->menu_id;
            $perm = $permissionNameMap->get($rp->permission_id);
            if ($perm) {
                $permName = strtolower($perm->name);
                if (! isset($matrix[$menuId])) {
                    $matrix[$menuId] = [];
                }
                $matrix[$menuId][$permName] = true;
                $assignedPermissionsCount++;

                if ($permName === 'view') {
                    $accessibleMenuIds[$menuId] = true;
                }
            }
        }

        $otherRoles = Role::where('role_id', '!=', $role->role_id)
            ->where('status', 'active')
            ->get(['role_id', 'name', 'description']);

        return response()->json([
            'role' => [
                'role_id' => $role->role_id,
                'id' => $role->role_id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'description' => $role->description,
                'status' => $role->status,
                'created_at' => $role->created_at ? $role->created_at->format('M d, Y') : null,
            ],
            'permissions' => $allPermissions,
            'menus' => $allMenus,
            'menu_tree' => $menuTree,
            'matrix' => $matrix,
            'other_roles' => $otherRoles,
            'stats' => [
                'total_modules' => $allMenus->count(),
                'accessible_modules' => count($accessibleMenuIds),
                'total_permissions' => $assignedPermissionsCount,
            ],
        ]);
    }

    /**
     * Save/sync the entire permissions matrix for a role.
     */
    public function saveMatrix(Request $request, int $role_id): JsonResponse
    {
        $role = Role::find($role_id);

        if (! $role) {
            return response()->json([
                'message' => 'Role not found',
            ], 404);
        }

        $validated = $request->validate([
            'assignments' => ['present', 'array'],
            'assignments.*.menu_id' => ['required', 'integer', 'exists:tkt_menus,id'],
            'assignments.*.permission_id' => ['required', 'integer', 'exists:permissions,permission_id'],
        ]);

        DB::transaction(function () use ($role, $validated) {
            RoleHasPermission::where('role_id', $role->role_id)->delete();

            $insertData = [];
            $seen = [];
            $now = now();

            foreach ($validated['assignments'] as $item) {
                $key = "{$item['menu_id']}_{$item['permission_id']}";
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $insertData[] = [
                    'role_id' => $role->role_id,
                    'permission_id' => (int) $item['permission_id'],
                    'menu_id' => (int) $item['menu_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($insertData)) {
                // Chunk insert to prevent parameter limits
                foreach (array_chunk($insertData, 500) as $chunk) {
                    RoleHasPermission::insert($chunk);
                }
            }
        });

        return response()->json([
            'message' => 'Role permissions saved successfully',
            'data' => $this->getMatrix($role->role_id)->getData(true),
        ]);
    }

    /**
     * Copy permissions from another role.
     */
    public function copyFrom(Request $request, int $role_id): JsonResponse
    {
        $targetRole = Role::find($role_id);

        if (! $targetRole) {
            return response()->json([
                'message' => 'Target role not found',
            ], 404);
        }

        $validated = $request->validate([
            'source_role_id' => ['required', 'integer', 'exists:roles,role_id'],
        ]);

        $sourceRoleId = (int) $validated['source_role_id'];

        DB::transaction(function () use ($targetRole, $sourceRoleId) {
            $sourcePermissions = RoleHasPermission::where('role_id', $sourceRoleId)->get();
            RoleHasPermission::where('role_id', $targetRole->role_id)->delete();

            $insertData = [];
            $now = now();

            foreach ($sourcePermissions as $src) {
                $insertData[] = [
                    'role_id' => $targetRole->role_id,
                    'permission_id' => $src->permission_id,
                    'menu_id' => $src->menu_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($insertData)) {
                foreach (array_chunk($insertData, 500) as $chunk) {
                    RoleHasPermission::insert($chunk);
                }
            }
        });

        return response()->json([
            'message' => 'Permissions copied successfully',
            'data' => $this->getMatrix($targetRole->role_id)->getData(true),
        ]);
    }
}
