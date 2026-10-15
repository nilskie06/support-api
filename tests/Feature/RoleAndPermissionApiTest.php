<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndPermissionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuSeeder::class);
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_can_list_roles(): void
    {
        $response = $this->getJson('/api/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'role_id',
                        'name',
                        'guard_name',
                        'description',
                        'status',
                        'stats' => [
                            'permissions_count',
                            'accessible_modules_count',
                            'total_modules_count',
                            'users_count',
                        ],
                    ],
                ],
                'stats' => ['total', 'active', 'inactive', 'total_permissions', 'total_modules'],
            ]);
    }

    public function test_can_create_role(): void
    {
        $payload = [
            'name' => 'manager',
            'guard_name' => 'web',
            'description' => 'General Manager',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/roles', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'manager');

        $this->assertDatabaseHas('roles', ['name' => 'manager']);
    }

    public function test_cannot_delete_admin_role(): void
    {
        $admin = Role::where('name', 'admin')->first();

        $response = $this->deleteJson("/api/roles/{$admin->role_id}");

        $response->assertStatus(403);
    }

    public function test_can_get_and_save_role_permissions_matrix(): void
    {
        $role = Role::create([
            'name' => 'auditor',
            'guard_name' => 'web',
            'description' => 'Internal Auditor',
            'status' => 'active',
        ]);

        $matrixResponse = $this->getJson("/api/roles/{$role->role_id}/matrix");
        $matrixResponse->assertStatus(200)
            ->assertJsonStructure([
                'role',
                'permissions',
                'menus',
                'menu_tree',
                'matrix',
                'stats',
            ]);

        $menu = Menu::first();
        $perm = Permission::first();

        $saveResponse = $this->postJson("/api/roles/{$role->role_id}/matrix", [
            'assignments' => [
                [
                    'menu_id' => $menu->id,
                    'permission_id' => $perm->permission_id,
                ],
            ],
        ]);

        $saveResponse->assertStatus(200);

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->role_id,
            'menu_id' => $menu->id,
            'permission_id' => $perm->permission_id,
        ]);
    }

    public function test_can_copy_permissions_from_another_role(): void
    {
        $admin = Role::where('name', 'admin')->first();
        $newRole = Role::create([
            'name' => 'lead_admin',
            'guard_name' => 'web',
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/roles/{$newRole->role_id}/copy-from", [
            'source_role_id' => $admin->role_id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $newRole->role_id,
        ]);
    }

    public function test_can_crud_permissions(): void
    {
        // 1. List
        $this->getJson('/api/permissions')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'stats']);

        // 2. Create
        $createRes = $this->postJson('/api/permissions', [
            'name' => 'export',
            'guard_name' => 'web',
            'description' => 'Export data to CSV/Excel',
            'status' => 'active',
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.name', 'export');

        $permId = $createRes->json('data.permission_id');

        // 3. Update
        $this->putJson("/api/permissions/{$permId}", [
            'name' => 'export_pdf',
            'description' => 'Export data to PDF',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'export_pdf');

        // 4. Delete
        $this->deleteJson("/api/permissions/{$permId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('permissions', ['permission_id' => $permId]);
    }
}
