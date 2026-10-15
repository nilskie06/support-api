<?php

namespace Database\Seeders;

use App\Models\System;
use Illuminate\Database\Seeder;

class SystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $systems = [
            [
                'code' => 'SYS-TMS',
                'name' => 'Transportation Management System',
                'status' => 'active',
            ],
            [
                'code' => 'SYS-WMS',
                'name' => 'Warehouse Management System',
                'status' => 'active',
            ],
            [
                'code' => 'SYS-CRM',
                'name' => 'Customer Relationship Management',
                'status' => 'active',
            ],
            [
                'code' => 'SYS-HRIS',
                'name' => 'Human Resource Information System',
                'status' => 'active',
            ],
            [
                'code' => 'SYS-LEGACY',
                'name' => 'Legacy Invoicing & Billing Tool',
                'status' => 'inactive',
            ],
        ];

        foreach ($systems as $system) {
            System::updateOrCreate(
                ['code' => $system['code']],
                $system
            );
        }
    }
}
