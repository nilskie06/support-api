<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserAssigned;
use Illuminate\Database\Seeder;

class UserAssignedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::first();
        if (! $admin) {
            return;
        }

        $assignments = [
            // Company assignments
            [
                'user_id' => $admin->id,
                'reference' => 'company',
                'reference_code' => 'AAI-WL',
                'default' => true,
            ],
            [
                'user_id' => $admin->id,
                'reference' => 'company',
                'reference_code' => 'ATN-01',
                'default' => false,
            ],
            // Department assignments
            [
                'user_id' => $admin->id,
                'reference' => 'department',
                'reference_code' => 'DEP-IT',
                'default' => true,
            ],
            [
                'user_id' => $admin->id,
                'reference' => 'department',
                'reference_code' => 'DEP-OPS',
                'default' => false,
            ],
            // Station assignments
            [
                'user_id' => $admin->id,
                'reference' => 'station',
                'reference_code' => 'STN-MNL',
                'default' => true,
            ],
            [
                'user_id' => $admin->id,
                'reference' => 'station',
                'reference_code' => 'STN-CRK',
                'default' => false,
            ],
            // System assignments
            [
                'user_id' => $admin->id,
                'reference' => 'system',
                'reference_code' => 'SYS-TMS',
                'default' => true,
            ],
            [
                'user_id' => $admin->id,
                'reference' => 'system',
                'reference_code' => 'SYS-WMS',
                'default' => false,
            ],
        ];

        foreach ($assignments as $assignment) {
            UserAssigned::updateOrCreate(
                [
                    'user_id' => $assignment['user_id'],
                    'reference' => $assignment['reference'],
                    'reference_code' => $assignment['reference_code'],
                ],
                $assignment
            );
        }
    }
}
