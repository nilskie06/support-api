<?php

namespace Tests\Feature;

use App\Models\System;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SystemApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_can_list_systems(): void
    {
        System::factory()->create([
            'code' => 'SYS-A',
            'name' => 'System Alpha',
            'status' => 'active',
        ]);
        System::factory()->create([
            'code' => 'SYS-B',
            'name' => 'System Beta',
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/systems');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.active', 1)
            ->assertJsonPath('stats.inactive', 1);
    }

    public function test_can_search_systems(): void
    {
        System::factory()->create([
            'code' => 'UNIQUE-TMS',
            'name' => 'Transportation System',
        ]);
        System::factory()->create([
            'code' => 'OTHER-WMS',
            'name' => 'Warehouse Management',
        ]);

        $response = $this->getJson('/api/systems?search=Transportation');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'UNIQUE-TMS');
    }

    public function test_can_filter_systems_by_status(): void
    {
        System::factory()->create(['status' => 'active']);
        System::factory()->create(['status' => 'inactive']);

        $response = $this->getJson('/api/systems?status=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_can_create_system(): void
    {
        $payload = [
            'code' => 'sys-crm',
            'name' => 'CRM Customer Portal',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/systems', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'SYS-CRM')
            ->assertJsonPath('data.name', 'CRM Customer Portal')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('systems', [
            'code' => 'SYS-CRM',
            'name' => 'CRM Customer Portal',
            'status' => 'active',
        ]);
    }

    public function test_cannot_create_system_with_duplicate_code(): void
    {
        System::factory()->create(['code' => 'DUP-SYS']);

        $response = $this->postJson('/api/systems', [
            'code' => 'DUP-SYS',
            'name' => 'Another System',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->postJson('/api/systems', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_can_show_system(): void
    {
        $system = System::factory()->create([
            'code' => 'SYS-SHOW',
            'name' => 'Show System',
        ]);

        $response = $this->getJson("/api/systems/{$system->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'SYS-SHOW')
            ->assertJsonPath('data.name', 'Show System');
    }

    public function test_can_update_system(): void
    {
        $system = System::factory()->create([
            'code' => 'ORIG-CODE',
            'name' => 'Original Name',
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/systems/{$system->id}", [
            'code' => 'UPD-CODE',
            'name' => 'Updated Name',
            'status' => 'inactive',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'UPD-CODE')
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('systems', [
            'id' => $system->id,
            'code' => 'UPD-CODE',
            'name' => 'Updated Name',
            'status' => 'inactive',
        ]);
    }

    public function test_can_soft_delete_system(): void
    {
        $system = System::factory()->create();

        $response = $this->deleteJson("/api/systems/{$system->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'System deleted successfully');

        $this->assertSoftDeleted('systems', [
            'id' => $system->id,
        ]);
    }

    public function test_cannot_delete_system_with_associated_tickets(): void
    {
        $system = System::factory()->create(['code' => 'SYS-TIKT']);
        Ticket::create([
            'ref_no' => 'TKT-TEST-99',
            'subject' => 'Issue with system',
            'summary' => 'System error occurred',
            'system' => 'SYS-TIKT',
            'status' => 'open',
        ]);

        $response = $this->deleteJson("/api/systems/{$system->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete system because it is associated with existing tickets.');

        $this->assertDatabaseHas('systems', [
            'id' => $system->id,
            'deleted_at' => null,
        ]);
    }
}
