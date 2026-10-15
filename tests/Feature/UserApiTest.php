<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Station;
use App\Models\System;
use App\Models\User;
use App\Models\UserAssigned;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_can_list_users(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        UserAssigned::create([
            'user_id' => $user->id,
            'reference' => 'company',
            'reference_code' => 'AAI-WL',
            'default' => true,
        ]);

        $response = $this->getJson('/api/users');

        $response->assertOk()
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.active', 1)
            ->assertJsonPath('data.0.name', 'John Doe')
            ->assertJsonPath('data.0.assignments.0.reference_code', 'AAI-WL');
    }

    public function test_can_search_users(): void
    {
        User::factory()->create(['name' => 'Alice Johnson', 'email' => 'alice@test.com']);
        User::factory()->create(['name' => 'Bob Smith', 'email' => 'bob@test.com']);

        $response = $this->getJson('/api/users?search=Alice');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alice Johnson');
    }

    public function test_can_filter_users_by_reference_assignment(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        UserAssigned::create([
            'user_id' => $user1->id,
            'reference' => 'company',
            'reference_code' => 'AAI-WL',
            'default' => true,
        ]);

        UserAssigned::create([
            'user_id' => $user2->id,
            'reference' => 'company',
            'reference_code' => 'OTHER-CO',
            'default' => false,
        ]);

        $response = $this->getJson('/api/users?reference=company&reference_code=AAI-WL');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user1->id);
    }

    public function test_can_get_lookups(): void
    {
        Company::factory()->create(['code' => 'COMP-1', 'status' => 'active']);
        Department::factory()->create(['code' => 'DEP-1', 'status' => 'active']);
        Station::factory()->create(['code' => 'STN-1', 'status' => 'active']);
        System::factory()->create(['code' => 'SYS-1', 'status' => 'active']);

        $response = $this->getJson('/api/users/lookups');

        $response->assertOk()
            ->assertJsonStructure(['companies', 'departments', 'stations', 'systems'])
            ->assertJsonCount(1, 'companies')
            ->assertJsonCount(1, 'departments')
            ->assertJsonCount(1, 'stations')
            ->assertJsonCount(1, 'systems');
    }

    public function test_can_create_user_with_assignments(): void
    {
        $payload = [
            'name' => 'Jane Logistics',
            'email' => 'jane@logistics.com',
            'password' => 'secret123',
            'status' => 'active',
            'assignments' => [
                [
                    'reference' => 'company',
                    'reference_code' => 'AAI-WL',
                    'default' => true,
                ],
                [
                    'reference' => 'department',
                    'reference_code' => 'DEP-IT',
                    'default' => true,
                ],
            ],
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Jane Logistics')
            ->assertJsonCount(2, 'data.assignments');

        $this->assertDatabaseHas('users', ['email' => 'jane@logistics.com']);
        $this->assertDatabaseHas('user_assigned', [
            'reference' => 'company',
            'reference_code' => 'AAI-WL',
            'default' => 1,
        ]);
    }

    public function test_can_update_user_and_assignments(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        UserAssigned::create([
            'user_id' => $user->id,
            'reference' => 'company',
            'reference_code' => 'OLD-CO',
            'default' => true,
        ]);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'New Name',
            'email' => $user->email,
            'status' => 'active',
            'assignments' => [
                [
                    'reference' => 'company',
                    'reference_code' => 'AAI-WL',
                    'default' => true,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonCount(1, 'data.assignments')
            ->assertJsonPath('data.assignments.0.reference_code', 'AAI-WL');

        $this->assertDatabaseMissing('user_assigned', ['reference_code' => 'OLD-CO']);
        $this->assertDatabaseHas('user_assigned', ['reference_code' => 'AAI-WL']);
    }

    public function test_can_sync_assignments(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/api/users/{$user->id}/assignments", [
            'assignments' => [
                [
                    'reference' => 'station',
                    'reference_code' => 'STN-MNL',
                    'default' => true,
                ],
                [
                    'reference' => 'system',
                    'reference_code' => 'SYS-TMS',
                    'default' => false,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.assignments');

        $this->assertDatabaseHas('user_assigned', [
            'user_id' => $user->id,
            'reference' => 'station',
            'reference_code' => 'STN-MNL',
        ]);
    }

    public function test_can_delete_user(): void
    {
        $user = User::factory()->create(['id' => 99]);
        UserAssigned::create([
            'user_id' => $user->id,
            'reference' => 'company',
            'reference_code' => 'AAI-WL',
            'default' => true,
        ]);

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'User deleted successfully');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('user_assigned', ['user_id' => $user->id]);
    }

    public function test_cannot_delete_primary_admin(): void
    {
        $admin = User::factory()->create(['id' => 1]);

        $response = $this->deleteJson("/api/users/{$admin->id}");

        $response->assertStatus(403);
    }
}
