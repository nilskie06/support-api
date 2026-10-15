<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\System;
use App\Models\TktType;
use Illuminate\Database\Seeder;

class TktTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed System Board types from systems table
        $systemBoard = Board::where('name', 'System')->first();
        $systemBoardId = $systemBoard ? $systemBoard->id : 1;

        $activeSystems = System::where('status', 'active')->get();
        foreach ($activeSystems as $sys) {
            TktType::updateOrCreate(
                [
                    'code' => $sys->code,
                    'board_id' => $systemBoardId,
                ],
                [
                    'name' => $sys->name,
                ]
            );
        }

        // 2. Seed Facilities Board types
        $facilitiesBoard = Board::where('name', 'Facilities')->first();
        $facilitiesBoardId = $facilitiesBoard ? $facilitiesBoard->id : 3;

        $facilitiesTypes = [
            [
                'code' => 'PLUMBING',
                'name' => 'Plumbing',
                'board_id' => $facilitiesBoardId,
            ],
            [
                'code' => 'LIGHTING',
                'name' => 'Lighting',
                'board_id' => $facilitiesBoardId,
            ],
            [
                'code' => 'HEAT_AC',
                'name' => 'Heat/AC',
                'board_id' => $facilitiesBoardId,
            ],
            [
                'code' => 'PESTS',
                'name' => 'Pests',
                'board_id' => $facilitiesBoardId,
            ],
            [
                'code' => 'SECURITY',
                'name' => 'Security',
                'board_id' => $facilitiesBoardId,
            ],
            [
                'code' => 'CLEANING',
                'name' => 'Cleaning',
                'board_id' => $facilitiesBoardId,
            ],
            [
                'code' => 'OTHER',
                'name' => 'Other',
                'board_id' => $facilitiesBoardId,
            ],
        ];

        foreach ($facilitiesTypes as $type) {
            TktType::updateOrCreate(
                [
                    'code' => $type['code'],
                    'board_id' => $type['board_id'],
                ],
                [
                    'name' => $type['name'],
                ]
            );
        }

        // 3. Seed Support Board types
        $supportBoard = Board::where('name', 'Support')->first();
        $supportBoardId = $supportBoard ? $supportBoard->id : 2;

        $supportTypes = [
            [
                'code' => 'INTERNET',
                'name' => 'LAN, WIFI',
                'board_id' => $supportBoardId,
            ],
            [
                'code' => 'LAPTOP',
                'name' => 'LAPTOP REPAIR',
                'board_id' => $supportBoardId,
            ],
        ];

        foreach ($supportTypes as $type) {
            TktType::updateOrCreate(
                [
                    'code' => $type['code'],
                    'board_id' => $type['board_id'],
                ],
                [
                    'name' => $type['name'],
                ]
            );
        }
    }
}
