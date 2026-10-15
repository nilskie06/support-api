<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CompanyApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_can_list_companies(): void
    {
        Company::factory()->create([
            'code' => 'COMP-A',
            'name' => 'Company Alpha',
            'status' => 'active',
        ]);
        Company::factory()->create([
            'code' => 'COMP-B',
            'name' => 'Company Beta',
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/companies');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.active', 1)
            ->assertJsonPath('stats.inactive', 1);
    }

    public function test_can_search_companies(): void
    {
        Company::factory()->create([
            'code' => 'UNIQUE-01',
            'name' => 'Logistics Corp',
        ]);
        Company::factory()->create([
            'code' => 'OTHER-02',
            'name' => 'Express Courier',
        ]);

        $response = $this->getJson('/api/companies?search=Logistics');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'UNIQUE-01');
    }

    public function test_can_filter_companies_by_status(): void
    {
        Company::factory()->create(['status' => 'active']);
        Company::factory()->create(['status' => 'inactive']);

        $response = $this->getJson('/api/companies?status=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_can_create_company(): void
    {
        $payload = [
            'code' => 'AAI-CORP',
            'name' => 'AAI Logistics Group',
            'address' => '77 Airport Rd, Pasay City',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/companies', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'AAI-CORP')
            ->assertJsonPath('data.name', 'AAI Logistics Group');

        $this->assertDatabaseHas('companies', [
            'code' => 'AAI-CORP',
            'name' => 'AAI Logistics Group',
            'status' => 'active',
        ]);
    }

    public function test_cannot_create_company_with_duplicate_code(): void
    {
        Company::factory()->create(['code' => 'DUP-CODE']);

        $response = $this->postJson('/api/companies', [
            'code' => 'DUP-CODE',
            'name' => 'Another Company',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_cannot_create_company_without_required_fields(): void
    {
        $response = $this->postJson('/api/companies', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_can_show_company(): void
    {
        $company = Company::factory()->create([
            'code' => 'SHOW-01',
            'name' => 'Show Company',
        ]);

        $response = $this->getJson("/api/companies/{$company->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonPath('data.code', 'SHOW-01');
    }

    public function test_returns_404_for_non_existent_company(): void
    {
        $response = $this->getJson('/api/companies/99999');

        $response->assertStatus(404);
    }

    public function test_can_update_company(): void
    {
        $company = Company::factory()->create([
            'code' => 'OLD-CODE',
            'name' => 'Old Company Name',
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/companies/{$company->id}", [
            'code' => 'NEW-CODE',
            'name' => 'Updated Company Name',
            'address' => 'New Address 123',
            'status' => 'inactive',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'NEW-CODE')
            ->assertJsonPath('data.name', 'Updated Company Name')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'code' => 'NEW-CODE',
            'name' => 'Updated Company Name',
            'status' => 'inactive',
        ]);
    }

    public function test_can_delete_company(): void
    {
        $company = Company::factory()->create();

        $response = $this->deleteJson("/api/companies/{$company->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Company deleted successfully');

        $this->assertSoftDeleted('companies', [
            'id' => $company->id,
        ]);
    }

    public function test_cannot_delete_company_assigned_to_tickets(): void
    {
        $company = Company::factory()->create();
        Ticket::create([
            'company_id' => $company->id,
            'ref_no' => 'TKT-001',
            'subject' => 'Test Ticket',
        ]);

        $response = $this->deleteJson("/api/companies/{$company->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'deleted_at' => null,
        ]);
    }
}
