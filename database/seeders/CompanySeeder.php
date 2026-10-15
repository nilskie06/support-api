<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'code' => 'AAI-WL',
                'name' => 'AAI Worldwide Logistics Inc.',
                'address' => 'AAI Center, 123 Logistics Ave, Paranaque City, Metro Manila',
                'status' => 'active',
            ],
            [
                'code' => 'ATN-01',
                'name' => 'All Transport Network Global',
                'address' => 'Terminal 2 Cargo Center, Pasay City, Philippines',
                'status' => 'active',
            ],
            [
                'code' => 'AEX-EXP',
                'name' => 'A-Express Delivery Services',
                'address' => 'Bldg 4 Freight District, Taguig City, Philippines',
                'status' => 'active',
            ],
            [
                'code' => 'GLO-FRT',
                'name' => 'Global Freight Solutions',
                'address' => '88 Port Area Boulevard, Manila, Philippines',
                'status' => 'inactive',
            ],
        ];

        foreach ($companies as $company) {
            Company::updateOrCreate(
                ['code' => $company['code']],
                $company
            );
        }
    }
}
