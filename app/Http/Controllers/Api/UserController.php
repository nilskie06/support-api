<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Company;
use App\Models\Department;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleHasPermission;
use App\Models\Station;
use App\Models\System;
use App\Models\User;
use App\Models\UserAssigned;
use App\Models\UserMenuAcces;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users with assignments and menu access.
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user() ?: auth('sanctum')->user();
        if ($authUser && ! $this->canUserAccessSettings($authUser)) {
            return response()->json([
                'message' => 'Forbidden: You do not have permission to view User Management.',
            ], 403);
        }

        $search = $request->query('search');
        $status = $request->query('status');
        $role = $request->query('role');
        $reference = $request->query('reference');
        $referenceCode = $request->query('reference_code');

        $query = User::with(['assignments', 'menuAccess']);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('fullname', 'like', "%{$search}%");
            });
        }

        if (! empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($role) && $role !== 'all') {
            $query->where('role', $role);
        }

        if (! empty($reference)) {
            $query->whereHas('assignments', function ($q) use ($reference, $referenceCode) {
                $q->where('reference', $reference);
                if (! empty($referenceCode)) {
                    $q->where('reference_code', $referenceCode);
                }
            });
        }

        $users = $query->orderBy('id', 'asc')->get();

        $roleCounts = [];
        $activeRoles = Role::where('status', 'active')->pluck('name');
        foreach ($activeRoles as $rName) {
            $roleCounts[$rName] = User::where('role', $rName)->count();
        }

        $stats = array_merge([
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
            'assigned' => User::has('assignments')->count(),
            'admin' => User::where('role', 'admin')->count(),
            'users' => User::where('role', 'users')->count(),
        ], $roleCounts);

        return response()->json([
            'data' => $users,
            'stats' => $stats,
        ]);
    }

    /**
     * Get lookup options for entity assignment, roles, permissions, and menus.
     */
    public function lookups(): JsonResponse
    {
        $roleModels = Role::where('status', 'active')
            ->orderBy('role_id')
            ->get(['role_id', 'name', 'guard_name', 'description']);

        $roles = $roleModels->pluck('name')->toArray();
        if (empty($roles)) {
            $roles = ['admin', 'users'];
        }

        $permissions = Permission::where('status', 'active')->pluck('name')->toArray();
        if (empty($permissions)) {
            $permissions = ['view', 'add', 'edit', 'delete'];
        }

        return response()->json([
            'companies' => Company::where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'departments' => Department::where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'stations' => Station::where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'systems' => System::where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'boards' => Board::where('status', true)->orderBy('name')->get(['id', 'name']),
            'roles' => $roles,
            'role_list' => $roleModels,
            'permissions' => $permissions,
            'menus' => Menu::orderBy('order')->get(['id', 'parent_id', 'code', 'name', 'icon', 'url', 'order', 'status']),
            'menu_tree' => Menu::root()->with('allChildren')->get(),
        ]);
    }

    /**
     * Store a newly created user along with entity assignments and menu permissions.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'fullname' => ['nullable', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'role' => ['nullable', 'string', 'max:50'],
            'assignments' => ['nullable', 'array'],
            'assignments.*.reference' => ['required', 'string', 'in:company,department,station,system,category'],
            'assignments.*.reference_code' => ['required', 'string', 'max:50'],
            'assignments.*.default' => ['nullable', 'boolean'],
            'menu_access' => ['nullable', 'array'],
            'menu_access.*.menu_id' => ['required', 'integer', 'exists:tkt_menus,id'],
            'menu_access.*.can_view' => ['nullable', 'boolean'],
            'menu_access.*.can_add' => ['nullable', 'boolean'],
            'menu_access.*.can_edit' => ['nullable', 'boolean'],
            'menu_access.*.can_delete' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => trim($validated['name']),
                'email' => trim($validated['email']),
                'username' => ! empty($validated['username']) ? trim($validated['username']) : null,
                'fullname' => ! empty($validated['fullname']) ? trim($validated['fullname']) : trim($validated['name']),
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'] ?? 'active',
                'role' => $validated['role'] ?? 'users',
            ]);

            if (! empty($validated['assignments'])) {
                $this->saveAssignments($user, $validated['assignments']);
            }

            if (isset($validated['menu_access'])) {
                $this->saveMenuAccess($user, $validated['menu_access']);
            } else {
                $this->syncUserMenuAccessFromRole($user);
            }

            return response()->json([
                'message' => 'User created successfully',
                'data' => $user->load(['assignments', 'menuAccess']),
            ], 201);
        });
    }

    /**
     * Display the specified user with assignments and menu access.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['assignments', 'menuAccess'])->find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'data' => $user,
        ]);
    }

    /**
     * Update the specified user, their entity assignments, and menu permissions.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'fullname' => ['nullable', 'string', 'max:150'],
            'password' => ['nullable', 'string', 'min:6'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'role' => ['nullable', 'string', 'max:50'],
            'assignments' => ['nullable', 'array'],
            'assignments.*.reference' => ['required', 'string', 'in:company,department,station,system,category'],
            'assignments.*.reference_code' => ['required', 'string', 'max:50'],
            'assignments.*.default' => ['nullable', 'boolean'],
            'menu_access' => ['nullable', 'array'],
            'menu_access.*.menu_id' => ['required', 'integer', 'exists:tkt_menus,id'],
            'menu_access.*.can_view' => ['nullable', 'boolean'],
            'menu_access.*.can_add' => ['nullable', 'boolean'],
            'menu_access.*.can_edit' => ['nullable', 'boolean'],
            'menu_access.*.can_delete' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($user, $validated, $request) {
            $updateData = [
                'name' => trim($validated['name']),
                'email' => trim($validated['email']),
                'username' => ! empty($validated['username']) ? trim($validated['username']) : null,
                'fullname' => ! empty($validated['fullname']) ? trim($validated['fullname']) : trim($validated['name']),
                'status' => $validated['status'] ?? $user->status ?? 'active',
                'role' => $validated['role'] ?? $user->role ?? 'users',
            ];

            if (! empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);

            if (array_key_exists('assignments', $validated)) {
                $user->assignments()->delete();
                if (! empty($validated['assignments'])) {
                    $this->saveAssignments($user, $validated['assignments']);
                }
            }

            if ($request->boolean('sync_role')) {
                $this->syncUserMenuAccessFromRole($user);
            } elseif (array_key_exists('menu_access', $validated)) {
                $user->menuAccess()->delete();
                if (! empty($validated['menu_access'])) {
                    $this->saveMenuAccess($user, $validated['menu_access']);
                }
            } elseif ($user->wasChanged('role')) {
                $this->syncUserMenuAccessFromRole($user);
            }

            return response()->json([
                'message' => 'User updated successfully',
                'data' => $user->fresh(['assignments', 'menuAccess']),
            ]);
        });
    }

    /**
     * Get menu access for a specific user formatted with tree and stats.
     */
    public function getMenuAccess(Request|int|null $request = null, ?int $id = null): JsonResponse
    {
        if (is_int($request)) {
            $id = $request;
            $request = request();
        } elseif ($request === null) {
            $request = request();
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $allMenus = Menu::orderBy('order')->get();
        $userAccessMap = UserMenuAcces::where('user_id', $user->id)
            ->get()
            ->keyBy('menu_id');

        $isAdmin = ($user->role === 'admin');
        $syncRole = $request->boolean('sync_role');

        // Load permissions assigned to this user's role from role_has_permissions
        $role = Role::where('name', $user->role)->first();
        $roleMatrix = [];
        if ($role) {
            $rolePerms = RoleHasPermission::where('role_id', $role->role_id)
                ->with('permission')
                ->get();
            foreach ($rolePerms as $rp) {
                if ($rp->permission) {
                    $pName = strtolower($rp->permission->name);
                    $roleMatrix[$rp->menu_id][$pName] = true;
                }
            }
        }

        $hasExplicitAccess = $userAccessMap->isNotEmpty() && ! $syncRole;

        $flatList = $allMenus->map(function (Menu $menu) use ($userAccessMap, $isAdmin, $roleMatrix, $hasExplicitAccess) {
            $access = $userAccessMap->get($menu->id);
            $rolePerms = $roleMatrix[$menu->id] ?? [];

            if ($isAdmin) {
                $canView = true;
                $canAdd = true;
                $canEdit = true;
                $canDelete = true;
            } elseif ($hasExplicitAccess && $access) {
                // Explicit user access overrides the permissions inherited from the role.
                $canView = (bool) $access->can_view;
                $canAdd = (bool) $access->can_add;
                $canEdit = (bool) $access->can_edit;
                $canDelete = (bool) $access->can_delete;
            } else {
                // Inherit directly from the assigned role
                $canView = ! empty($rolePerms['view']);
                $canAdd = ! empty($rolePerms['add']);
                $canEdit = ! empty($rolePerms['edit']);
                $canDelete = ! empty($rolePerms['delete']);
            }

            return [
                'id' => $menu->id,
                'menu_id' => $menu->id,
                'parent_id' => $menu->parent_id,
                'code' => $menu->code,
                'name' => $menu->name,
                'icon' => $menu->icon,
                'url' => $menu->url,
                'order' => $menu->order,
                'status' => (bool) $menu->status,
                'is_side_menu' => (bool) $menu->is_side_menu,
                'can_view' => $canView,
                'can_add' => $canAdd,
                'can_edit' => $canEdit,
                'can_delete' => $canDelete,
            ];
        });

        $tree = $this->buildMenuAccessTree($flatList);
        $accessibleCount = $flatList->filter(fn ($m) => $m['can_view'])->count();
        $totalCount = $flatList->count();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'roles' => Role::where('status', 'active')->pluck('name')->toArray(),
            'role_matrix' => $roleMatrix,
            'permissions' => ['view', 'add', 'edit', 'delete'],
            'menus' => $flatList,
            'menu_tree' => $tree,
            'stats' => [
                'accessible_count' => $accessibleCount,
                'total_count' => $totalCount,
            ],
        ]);
    }

    /**
     * Get menu access for the currently authenticated Sanctum user.
     */
    public function currentUserMenuAccess(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        return $this->getMenuAccess($request, $user->id);
    }

    /**
     * Get systems assigned to current authenticated user.
     */
    public function currentUserSystems(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $assignments = $user->assignedSystems()->get();
        $assignedCodes = $assignments->pluck('reference_code')->toArray();

        $systems = System::whereIn('code', $assignedCodes)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($system) use ($assignments) {
                $match = $assignments->firstWhere('reference_code', $system->code);
                $system->is_default = $match ? (bool) $match->default : false;

                return $system;
            });

        return response()->json([
            'data' => $systems,
            'total' => $systems->count(),
        ]);
    }

    /**
     * Sync or update menu access permissions for a user.
     */
    public function syncMenuAccess(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $validated = $request->validate([
            'menu_access' => ['present', 'array'],
            'menu_access.*.menu_id' => ['required', 'integer', 'exists:tkt_menus,id'],
            'menu_access.*.can_view' => ['nullable', 'boolean'],
            'menu_access.*.can_add' => ['nullable', 'boolean'],
            'menu_access.*.can_edit' => ['nullable', 'boolean'],
            'menu_access.*.can_delete' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->menuAccess()->delete();
            $this->saveMenuAccess($user, $validated['menu_access']);
        });

        return response()->json([
            'message' => 'Menu access permissions updated successfully',
            'data' => $this->getMenuAccess($request, $user->id)->getData(true),
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if ($user->id === 1) {
            return response()->json([
                'message' => 'Cannot delete the primary administrator account.',
            ], 403);
        }

        $user->assignments()->delete();
        $user->menuAccess()->delete();
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Sync or update assignments for a user.
     */
    public function syncAssignments(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $validated = $request->validate([
            'assignments' => ['present', 'array'],
            'assignments.*.reference' => ['required', 'string', 'in:company,department,station,system,category'],
            'assignments.*.reference_code' => ['required', 'string', 'max:50'],
            'assignments.*.default' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $user->assignments()->delete();
            $this->saveAssignments($user, $validated['assignments']);
        });

        return response()->json([
            'message' => 'Assignments updated successfully',
            'data' => $user->fresh('assignments'),
        ]);
    }

    /**
     * Helper to insert user assignments ensuring unique records.
     *
     * @param  array<int, array<string, mixed>>  $assignments
     */
    private function saveAssignments(User $user, array $assignments): void
    {
        $seen = [];
        foreach ($assignments as $item) {
            $ref = strtolower(trim($item['reference']));
            $code = strtoupper(trim($item['reference_code']));
            $key = "{$ref}_{$code}";

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            UserAssigned::create([
                'user_id' => $user->id,
                'reference' => $ref,
                'reference_code' => $code,
                'default' => ! empty($item['default']),
            ]);
        }
    }

    /**
     * Helper to insert user menu access rules ensuring unique records.
     *
     * @param  array<int, array<string, mixed>>  $accessList
     */
    private function saveMenuAccess(User $user, array $accessList): void
    {
        $seen = [];
        foreach ($accessList as $item) {
            $menuId = (int) $item['menu_id'];
            if (isset($seen[$menuId])) {
                continue;
            }
            $seen[$menuId] = true;

            UserMenuAcces::create([
                'user_id' => $user->id,
                'menu_id' => $menuId,
                'can_view' => ! empty($item['can_view']),
                'can_add' => ! empty($item['can_add']),
                'can_edit' => ! empty($item['can_edit']),
                'can_delete' => ! empty($item['can_delete']),
            ]);
        }
    }

    /**
     * Sync user menu access records with their assigned role's permissions matrix.
     */
    public function syncUserMenuAccessFromRole(User $user): void
    {
        $user->menuAccess()->delete();

        if ($user->role === 'admin') {
            $this->grantFullMenuAccess($user);

            return;
        }

        $role = Role::where('name', $user->role)->first();
        if (! $role) {
            return;
        }

        $rolePerms = RoleHasPermission::where('role_id', $role->role_id)
            ->with('permission')
            ->get();

        $matrix = [];
        foreach ($rolePerms as $rp) {
            if ($rp->permission) {
                $pName = strtolower($rp->permission->name);
                $matrix[$rp->menu_id][$pName] = true;
            }
        }

        foreach ($matrix as $menuId => $perms) {
            UserMenuAcces::create([
                'user_id' => $user->id,
                'menu_id' => $menuId,
                'can_view' => ! empty($perms['view']),
                'can_add' => ! empty($perms['add']),
                'can_edit' => ! empty($perms['edit']),
                'can_delete' => ! empty($perms['delete']),
            ]);
        }
    }

    /**
     * Grant full access to all menus for an admin user.
     */
    private function grantFullMenuAccess(User $user): void
    {
        $menus = Menu::all();
        foreach ($menus as $menu) {
            UserMenuAcces::create([
                'user_id' => $user->id,
                'menu_id' => $menu->id,
                'can_view' => true,
                'can_add' => true,
                'can_edit' => true,
                'can_delete' => true,
            ]);
        }
    }

    /**
     * Recursively build menu access tree hierarchy.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildMenuAccessTree($items, int $parentId = 0): array
    {
        $branch = [];
        foreach ($items as $item) {
            $itemParentId = (int) ($item['parent_id'] ?? 0);
            if ($itemParentId === $parentId) {
                $children = $this->buildMenuAccessTree($items, (int) $item['menu_id']);
                $item['children'] = $children;
                $branch[] = $item;
            }
        }

        return $branch;
    }

    /**
     * Check if a user is authorized to access User Management.
     */
    private function canUserAccessSettings(User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $menu = Menu::where('url', '/settings/users')->orWhere('code', 'user-settings')->first();
        if (! $menu) {
            return true;
        }

        $access = UserMenuAcces::where('user_id', $user->id)
            ->where('menu_id', $menu->id)
            ->first();

        if ($access !== null) {
            return (bool) $access->can_view;
        }

        $role = Role::where('name', $user->role)->first();
        if ($role) {
            return RoleHasPermission::where('role_id', $role->role_id)
                ->where('menu_id', $menu->id)
                ->whereHas('permission', function ($q) {
                    $q->whereRaw('LOWER(name) = ?', ['view']);
                })
                ->exists();
        }

        return false;
    }
}
