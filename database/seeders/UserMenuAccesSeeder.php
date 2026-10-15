<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\User;
use App\Models\UserMenuAcces;
use Illuminate\Database\Seeder;

class UserMenuAccesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::find(1);
        if ($admin) {
            $admin->update(['role' => 'admin']);

            $menus = Menu::all();
            foreach ($menus as $menu) {
                UserMenuAcces::updateOrCreate(
                    [
                        'user_id' => $admin->id,
                        'menu_id' => $menu->id,
                    ],
                    [
                        'can_view' => true,
                        'can_add' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                    ]
                );
            }
        }
    }
}
