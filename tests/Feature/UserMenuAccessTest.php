<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use App\Models\UserMenuAcces;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserMenuAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_can_get_lookups_with_roles_permissions_and_menus(): void
    {
        Menu::create([
            'parent_id' => 0,
            'code' => 'dashboards',
            'name' => 'Dashboards',
            'order' => 1,
            'status' => true,
        ]);

        $response = $this->getJson('/api/users/lookups');

        $response->assertOk()
            ->assertJsonPath('roles.0', 'admin')
            ->assertJsonPath('roles.1', 'users')
            ->assertJsonPath('permissions.0', 'view')
            ->assertJsonPath('permissions.1', 'add')
            ->assertJsonPath('permissions.2', 'edit')
            ->assertJsonPath('permissions.3', 'delete')
            ->assertJsonStructure([
                'roles',
                'permissions',
                'menus',
                'menu_tree',
            ]);
    }

    public function test_can_create_user_with_role_and_menu_access(): void
    {
        $menu1 = Menu::create([
            'parent_id' => 0,
            'code' => 'dashboard_test',
            'name' => 'Dashboard Test',
            'order' => 1,
            'status' => true,
        ]);

        $menu2 = Menu::create([
            'parent_id' => $menu1->id,
            'code' => 'analytics_test',
            'name' => 'Analytics Test',
            'order' => 2,
            'status' => true,
        ]);

        $payload = [
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'username' => 'staffuser',
            'password' => 'secret123',
            'role' => 'users',
            'status' => 'active',
            'menu_access' => [
                [
                    'menu_id' => $menu1->id,
                    'can_view' => true,
                    'can_add' => false,
                    'can_edit' => false,
                    'can_delete' => false,
                ],
                [
                    'menu_id' => $menu2->id,
                    'can_view' => true,
                    'can_add' => true,
                    'can_edit' => true,
                    'can_delete' => false,
                ],
            ],
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Staff Member')
            ->assertJsonPath('data.role', 'users');

        $this->assertDatabaseHas('users', [
            'email' => 'staff@example.com',
            'role' => 'users',
        ]);

        $this->assertDatabaseHas('user_menu_acces', [
            'menu_id' => $menu1->id,
            'can_view' => 1,
            'can_add' => 0,
        ]);

        $this->assertDatabaseHas('user_menu_acces', [
            'menu_id' => $menu2->id,
            'can_view' => 1,
            'can_add' => 1,
            'can_edit' => 1,
            'can_delete' => 0,
        ]);
    }

    public function test_can_get_user_menu_access_with_tree_and_stats(): void
    {
        $user = User::factory()->create([
            'role' => 'users',
        ]);

        $parent = Menu::create([
            'parent_id' => 0,
            'code' => 'crm_parent',
            'name' => 'CRM Parent',
            'order' => 1,
            'status' => true,
        ]);

        $child = Menu::create([
            'parent_id' => $parent->id,
            'code' => 'crm_deals',
            'name' => 'CRM Deals',
            'order' => 2,
            'status' => true,
        ]);

        UserMenuAcces::create([
            'user_id' => $user->id,
            'menu_id' => $parent->id,
            'can_view' => true,
            'can_add' => false,
            'can_edit' => false,
            'can_delete' => false,
        ]);

        $response = $this->getJson("/api/users/{$user->id}/menu-access");

        $response->assertOk()
            ->assertJsonPath('user.role', 'users')
            ->assertJsonPath('stats.accessible_count', 1)
            ->assertJsonPath('stats.total_count', 2)
            ->assertJsonStructure([
                'user',
                'roles',
                'permissions',
                'menus',
                'menu_tree',
                'stats',
            ]);
    }

    public function test_can_sync_menu_access_permissions(): void
    {
        $user = User::factory()->create();

        $menu = Menu::create([
            'parent_id' => 0,
            'code' => 'settings_sync',
            'name' => 'Settings Sync',
            'order' => 1,
            'status' => true,
        ]);

        $payload = [
            'menu_access' => [
                [
                    'menu_id' => $menu->id,
                    'can_view' => true,
                    'can_add' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                ],
            ],
        ];

        $response = $this->postJson("/api/users/{$user->id}/menu-access", $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Menu access permissions updated successfully');

        $this->assertDatabaseHas('user_menu_acces', [
            'user_id' => $user->id,
            'menu_id' => $menu->id,
            'can_view' => 1,
            'can_add' => 1,
            'can_edit' => 1,
            'can_delete' => 1,
        ]);
    }

    public function test_authenticated_user_can_get_their_menu_access(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        Menu::create([
            'parent_id' => 0,
            'code' => 'auth_menu_test',
            'name' => 'Auth Menu Test',
            'order' => 1,
            'status' => true,
            'is_side_menu' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user/menu-access');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure([
                'user',
                'roles',
                'permissions',
                'menus',
                'menu_tree',
                'stats',
            ]);
    }
}
