<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DepartmentApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_can_list_departments(): void
    {
        Department::factory()->create([
            'code' => 'DEP-A',
            'name' => 'Department Alpha',
            'status' => 'active',
        ]);
        Department::factory()->create([
            'code' => 'DEP-B',
            'name' => 'Department Beta',
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/departments');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.active', 1)
            ->assertJsonPath('stats.inactive', 1);
    }

    public function test_can_search_departments(): void
    {
        Department::factory()->create([
            'code' => 'UNIQUE-IT',
            'name' => 'Information Technology',
        ]);
        Department::factory()->create([
            'code' => 'OTHER-HR',
            'name' => 'Human Resources',
        ]);

        $response = $this->getJson('/api/departments?search=Technology');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'UNIQUE-IT');
    }

    public function test_can_filter_departments_by_status(): void
    {
        Department::factory()->create(['status' => 'active']);
        Department::factory()->create(['status' => 'inactive']);

        $response = $this->getJson('/api/departments?status=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_can_create_department(): void
    {
        $payload = [
            'code' => 'dep-ops',
            'name' => 'Operations Department',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/departments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'DEP-OPS')
            ->assertJsonPath('data.name', 'Operations Department')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('departments', [
            'code' => 'DEP-OPS',
            'name' => 'Operations Department',
            'status' => 'active',
        ]);
    }

    public function test_cannot_create_department_with_duplicate_code(): void
    {
        Department::factory()->create(['code' => 'DUP-DEP']);

        $response = $this->postJson('/api/departments', [
            'code' => 'DUP-DEP',
            'name' => 'Another Department',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->postJson('/api/departments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_can_show_department(): void
    {
        $department = Department::factory()->create([
            'code' => 'DEP-SHOW',
            'name' => 'Show Department',
        ]);

        $response = $this->getJson("/api/departments/{$department->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'DEP-SHOW')
            ->assertJsonPath('data.name', 'Show Department');
    }

    public function test_can_update_department(): void
    {
        $department = Department::factory()->create([
            'code' => 'ORIG-DEP',
            'name' => 'Original Department',
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/departments/{$department->id}", [
            'code' => 'UPD-DEP',
            'name' => 'Updated Department',
            'status' => 'inactive',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'UPD-DEP')
            ->assertJsonPath('data.name', 'Updated Department')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'code' => 'UPD-DEP',
            'name' => 'Updated Department',
            'status' => 'inactive',
        ]);
    }

    public function test_can_soft_delete_department(): void
    {
        $department = Department::factory()->create();

        $response = $this->deleteJson("/api/departments/{$department->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Department deleted successfully');

        $this->assertSoftDeleted('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_cannot_delete_department_with_associated_tickets(): void
    {
        $department = Department::factory()->create();
        Ticket::create([
            'ref_no' => 'TKT-DEP-01',
            'subject' => 'Ticket in department',
            'summary' => 'Department ticket description',
            'department_id' => $department->id,
            'status' => 'open',
        ]);

        $response = $this->deleteJson("/api/departments/{$department->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete department because it is associated with existing tickets.');

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'deleted_at' => null,
        ]);
    }
}
