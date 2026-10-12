<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Menu::count() > 0) {
            return;
        }

        $dashboard = Menu::create([
            'parent_id' => 0,
            'name' => 'Dashboard',
            'code' => 'dashboard',
            'icon' => 'ri-dashboard-2-line',
            'url' => '/',
            'order' => 1,
            'status' => true,
            'is_side_menu' => true,
        ]);

        Menu::create([
            'parent_id' => $dashboard->id,
            'name' => 'Analytics',
            'code' => 'dashboard_analytics',
            'icon' => 'ri-bar-chart-2-line',
            'url' => '/dashboard/analytics',
            'order' => 1,
            'status' => true,
            'is_side_menu' => true,
        ]);

        Menu::create([
            'parent_id' => $dashboard->id,
            'name' => 'CRM',
            'code' => 'dashboard_crm',
            'icon' => 'ri-customer-service-2-line',
            'url' => '/dashboard/crm',
            'order' => 2,
            'status' => true,
            'is_side_menu' => true,
        ]);

        $tickets = Menu::create([
            'parent_id' => 0,
            'name' => 'Tickets',
            'code' => 'tickets',
            'icon' => 'ri-ticket-2-line',
            'url' => '/tickets/list',
            'order' => 2,
            'status' => true,
            'is_side_menu' => true,
        ]);

        Menu::create([
            'parent_id' => $tickets->id,
            'name' => 'Ticket List',
            'code' => 'ticket_list',
            'icon' => 'ri-list-check',
            'url' => '/tickets/list',
            'order' => 1,
            'status' => true,
            'is_side_menu' => true,
        ]);

        Menu::create([
            'parent_id' => 0,
            'name' => 'Menu Management',
            'code' => 'menu_management',
            'icon' => 'ri-menu-2-line',
            'url' => '/menu',
            'order' => 3,
            'status' => true,
            'is_side_menu' => true,
        ]);

        $settings = Menu::create([
            'parent_id' => 0,
            'name' => 'Settings',
            'code' => 'settings',
            'icon' => 'ri-settings-4-line',
            'url' => '#',
            'order' => 4,
            'status' => true,
            'is_side_menu' => true,
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'name' => 'General',
            'code' => 'settings_general',
            'icon' => 'ri-toggle-line',
            'url' => '/pages/profile/setting',
            'order' => 1,
            'status' => true,
            'is_side_menu' => true,
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'name' => 'System Configuration',
            'code' => 'system_configuration',
            'icon' => 'ri-tools-line',
            'url' => '/system-configuration',
            'order' => 2,
            'status' => true,
            'is_side_menu' => true,
        ]);

        $security = Menu::create([
            'parent_id' => $settings->id,
            'name' => 'Security',
            'code' => 'settings_security',
            'icon' => 'ri-shield-keyhole-line',
            'url' => '#',
            'order' => 2,
            'status' => true,
            'is_side_menu' => true,
        ]);

        Menu::create([
            'parent_id' => $security->id,
            'name' => 'Two-Factor Auth',
            'code' => 'security_2fa',
            'icon' => 'ri-lock-password-line',
            'url' => '/settings/security/2fa',
            'order' => 1,
            'status' => true,
            'is_side_menu' => true,
        ]);
    }
}
