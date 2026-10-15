<?php

namespace Tests\Feature;

use App\Models\Station;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StationApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_can_list_stations(): void
    {
        Station::factory()->create([
            'code' => 'STN-A',
            'name' => 'Station Alpha',
            'status' => 'active',
        ]);
        Station::factory()->create([
            'code' => 'STN-B',
            'name' => 'Station Beta',
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/stations');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.active', 1)
            ->assertJsonPath('stats.inactive', 1);
    }

    public function test_can_search_stations(): void
    {
        Station::factory()->create([
            'code' => 'UNIQUE-MNL',
            'name' => 'Manila Freight Station',
        ]);
        Station::factory()->create([
            'code' => 'OTHER-CEB',
            'name' => 'Cebu Port Station',
        ]);

        $response = $this->getJson('/api/stations?search=Manila');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'UNIQUE-MNL');
    }

    public function test_can_filter_stations_by_status(): void
    {
        Station::factory()->create(['status' => 'active']);
        Station::factory()->create(['status' => 'inactive']);

        $response = $this->getJson('/api/stations?status=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_can_create_station(): void
    {
        $payload = [
            'code' => 'stn-crk',
            'name' => 'Clark Hub Station',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/stations', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'STN-CRK')
            ->assertJsonPath('data.name', 'Clark Hub Station')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('stations', [
            'code' => 'STN-CRK',
            'name' => 'Clark Hub Station',
            'status' => 'active',
        ]);
    }

    public function test_cannot_create_station_with_duplicate_code(): void
    {
        Station::factory()->create(['code' => 'DUP-STN']);

        $response = $this->postJson('/api/stations', [
            'code' => 'DUP-STN',
            'name' => 'Another Station',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->postJson('/api/stations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_can_show_station(): void
    {
        $station = Station::factory()->create([
            'code' => 'STN-SHOW',
            'name' => 'Show Station',
        ]);

        $response = $this->getJson("/api/stations/{$station->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'STN-SHOW')
            ->assertJsonPath('data.name', 'Show Station');
    }

    public function test_can_update_station(): void
    {
        $station = Station::factory()->create([
            'code' => 'ORIG-STN',
            'name' => 'Original Station',
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/stations/{$station->id}", [
            'code' => 'UPD-STN',
            'name' => 'Updated Station',
            'status' => 'inactive',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'UPD-STN')
            ->assertJsonPath('data.name', 'Updated Station')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('stations', [
            'id' => $station->id,
            'code' => 'UPD-STN',
            'name' => 'Updated Station',
            'status' => 'inactive',
        ]);
    }

    public function test_can_soft_delete_station(): void
    {
        $station = Station::factory()->create();

        $response = $this->deleteJson("/api/stations/{$station->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Station deleted successfully');

        $this->assertSoftDeleted('stations', [
            'id' => $station->id,
        ]);
    }
}
