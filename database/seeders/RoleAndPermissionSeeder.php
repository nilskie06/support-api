<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleHasPermission;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Permissions matching matrix screenshot
        $permissionsList = [
            ['name' => 'view', 'description' => 'View and access module records and views'],
            ['name' => 'add', 'description' => 'Create new records in the module'],
            ['name' => 'edit', 'description' => 'Update and modify existing records'],
            ['name' => 'modify', 'description' => 'Advanced modification and configuration overrides'],
            ['name' => 'delete', 'description' => 'Remove records from the system'],
            ['name' => 'post', 'description' => 'Post or submit transactions for processing'],
            ['name' => 'unpost', 'description' => 'Revert posted transactions to draft status'],
            ['name' => 'lock', 'description' => 'Lock records to prevent further edits'],
            ['name' => 'unlock', 'description' => 'Unlock previously locked records'],
        ];

        $permissionModels = [];
        foreach ($permissionsList as $permData) {
            $permissionModels[$permData['name']] = Permission::updateOrCreate(
                ['name' => $permData['name']],
                [
                    'guard_name' => 'web',
                    'description' => $permData['description'],
                    'status' => 'active',
                ]
            );
        }

        // 2. Seed Roles
        $rolesList = [
            [
                'name' => 'admin',
                'description' => 'Administrator with complete access to all modules and configurations',
                'status' => 'active',
            ],
            [
                'name' => 'supervisor',
                'description' => 'Supervisor with management and approval capabilities',
                'status' => 'active',
            ],
            [
                'name' => 'agent',
                'description' => 'Support Desk Agent handling customer tickets and queries',
                'status' => 'active',
            ],
            [
                'name' => 'users',
                'description' => 'Standard user with basic viewing and ticket submission permissions',
                'status' => 'active',
            ],
        ];

        $roleModels = [];
        foreach ($rolesList as $rData) {
            $roleModels[$rData['name']] = Role::updateOrCreate(
                ['name' => $rData['name']],
                [
                    'guard_name' => 'web',
                    'description' => $rData['description'],
                    'status' => $rData['status'],
                ]
            );
        }

        // 3. Ensure Settings menu has Roles and Permissions links
        $settingsMenu = Menu::where('code', 'settings')->first();
        $settingsId = $settingsMenu ? $settingsMenu->id : 7;

        Menu::updateOrCreate(
            ['code' => 'role-settings'],
            [
                'parent_id' => $settingsId,
                'name' => 'Roles',
                'icon' => 'ri-shield-user-line',
                'url' => '/settings/roles',
                'order' => 11,
                'status' => true,
                'is_side_menu' => true,
            ]
        );

        Menu::updateOrCreate(
            ['code' => 'permission-settings'],
            [
                'parent_id' => $settingsId,
                'name' => 'Permissions',
                'icon' => 'ri-key-2-line',
                'url' => '/settings/permissions',
                'order' => 12,
                'status' => true,
                'is_side_menu' => true,
            ]
        );

        // 4. Seed role_has_permissions
        $allMenus = Menu::all();
        $allPermissions = Permission::all();

        // Grant full access to Admin role
        $adminRole = $roleModels['admin'];
        foreach ($allMenus as $menu) {
            foreach ($allPermissions as $perm) {
                RoleHasPermission::firstOrCreate([
                    'role_id' => $adminRole->role_id,
                    'permission_id' => $perm->permission_id,
                    'menu_id' => $menu->id,
                ]);
            }
        }

        // Grant View to Users role (limited to dashboard & tickets)
        $usersRole = $roleModels['users'];
        $viewPerm = $permissionModels['view'];
        if ($usersRole && $viewPerm) {
            foreach ([1, 2, 4, 5] as $menuId) {
                RoleHasPermission::firstOrCreate([
                    'role_id' => $usersRole->role_id,
                    'permission_id' => $viewPerm->permission_id,
                    'menu_id' => $menuId,
                ]);
            }
        }

        // Grant Agent role (Dashboard + Tickets: View, Add, Edit)
        $agentRole = $roleModels['agent'];
        if ($agentRole) {
            $agentPermNames = ['view', 'add', 'edit'];
            foreach ([1, 2, 4, 5] as $menuId) {
                foreach ($agentPermNames as $pName) {
                    if (isset($permissionModels[$pName])) {
                        RoleHasPermission::firstOrCreate([
                            'role_id' => $agentRole->role_id,
                            'permission_id' => $permissionModels[$pName]->permission_id,
                            'menu_id' => $menuId,
                        ]);
                    }
                }
            }
        }

        // Grant Supervisor role (Dashboard, CRM, Tickets, Board, Issue Types)
        $supervisorRole = $roleModels['supervisor'];
        if ($supervisorRole) {
            $supervisorPermNames = ['view', 'add', 'edit', 'modify', 'delete'];
            foreach ([1, 2, 3, 4, 5, 12, 13] as $menuId) {
                foreach ($supervisorPermNames as $pName) {
                    if (isset($permissionModels[$pName])) {
                        RoleHasPermission::firstOrCreate([
                            'role_id' => $supervisorRole->role_id,
                            'permission_id' => $permissionModels[$pName]->permission_id,
                            'menu_id' => $menuId,
                        ]);
                    }
                }
            }
        }
    }
}
