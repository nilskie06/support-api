<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'code' => 'DEP-IT',
                'name' => 'Information Technology Department',
                'status' => 'active',
            ],
            [
                'code' => 'DEP-OPS',
                'name' => 'Operations & Logistics Department',
                'status' => 'active',
            ],
            [
                'code' => 'DEP-FIN',
                'name' => 'Finance & Accounting Department',
                'status' => 'active',
            ],
            [
                'code' => 'DEP-HR',
                'name' => 'Human Resources Department',
                'status' => 'active',
            ],
            [
                'code' => 'DEP-CS',
                'name' => 'Customer Support & Solutions',
                'status' => 'active',
            ],
            [
                'code' => 'DEP-ARC',
                'name' => 'Archived Records Department',
                'status' => 'inactive',
            ],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                $department
            );
        }
    }
}
