<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stations = [
            [
                'code' => 'STN-MNL',
                'name' => 'Manila Central Logistics Station',
                'status' => 'active',
            ],
            [
                'code' => 'STN-CEB',
                'name' => 'Cebu Visayas Terminal Station',
                'status' => 'active',
            ],
            [
                'code' => 'STN-DVO',
                'name' => 'Davao Mindanao Freight Station',
                'status' => 'active',
            ],
            [
                'code' => 'STN-CRK',
                'name' => 'Clark International Airport Hub',
                'status' => 'active',
            ],
            [
                'code' => 'STN-SUB',
                'name' => 'Subic Bay Freeport Gateway',
                'status' => 'active',
            ],
            [
                'code' => 'STN-OLD',
                'name' => 'Old Domestic Cargo Terminal',
                'status' => 'inactive',
            ],
        ];

        foreach ($stations as $station) {
            Station::updateOrCreate(
                ['code' => $station['code']],
                $station
            );
        }
    }
}
