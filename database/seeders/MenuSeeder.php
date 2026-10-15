<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Seed the current menu structure. This seeder intentionally leaves an
     * existing menu table untouched; it is a baseline for fresh databases.
     */
    public function run(): void
    {
        if (Menu::query()->exists()) {
            return;
        }

        $roots = [
            ['name' => 'Dashboard', 'code' => 'dashboard', 'icon' => 'ri-dashboard-2-line', 'url' => '/dashboard/analytics', 'order' => 0],
            ['name' => 'Kanban Board', 'code' => 'ticket_kanban', 'icon' => 'ri-artboard-2-line', 'url' => '/kanban?board=system', 'order' => 1],
            ['name' => 'New', 'code' => 'tickets', 'icon' => 'ri-ticket-2-line', 'url' => '/tickets/new', 'order' => 2],
            ['name' => 'Ticket List', 'code' => 'ticket_list', 'icon' => 'ri-list-check', 'url' => '/tickets/list', 'order' => 3],
            ['name' => 'Chat', 'code' => 'chat', 'icon' => 'ri-chat-3-line', 'url' => '/chat', 'order' => 4],
            ['name' => 'Reports', 'code' => 'reports', 'icon' => 'ri-file-chart-line', 'url' => null, 'order' => 5],
            ['name' => 'Settings', 'code' => 'settings', 'icon' => 'ri-settings-4-line', 'url' => '#', 'order' => 6],
        ];

        $menuIds = [];
        foreach ($roots as $menu) {
            $menuIds[$menu['code']] = Menu::create([
                ...$menu,
                'parent_id' => 0,
                'status' => true,
                'is_side_menu' => true,
            ])->id;
        }

        $children = [
            ['parent' => 'reports', 'name' => 'General Report', 'code' => 'general-reports', 'icon' => 'ri-file-chart-line', 'url' => '/reports/general-report', 'order' => 0],
            ['parent' => 'settings', 'name' => 'Menus', 'code' => 'menu_management', 'icon' => 'ri-menu-2-line', 'url' => '/menu', 'order' => 0],
            ['parent' => 'settings', 'name' => 'System Config', 'code' => 'system_configuration', 'icon' => 'ri-tools-line', 'url' => '/system-configuration', 'order' => 1],
            ['parent' => 'settings', 'name' => 'Category', 'code' => 'settings_board', 'icon' => 'ri-artboard-line', 'url' => '/settings/board', 'order' => 2],
            ['parent' => 'settings', 'name' => 'Issue Types', 'code' => 'settings_issue_type', 'icon' => 'ri-price-tag-3-line', 'url' => '/settings/issue-type', 'order' => 3],
            ['parent' => 'settings', 'name' => 'Ticket Types', 'code' => 'settings_type', 'icon' => 'ri-price-tag-line', 'url' => '/settings/type', 'order' => 4],
            ['parent' => 'settings', 'name' => 'Companies', 'code' => 'company-settings', 'icon' => 'ri-building-line', 'url' => '/settings/company', 'order' => 5],
            ['parent' => 'settings', 'name' => 'Severities', 'code' => 'settings_severities', 'icon' => 'ri-shield-star-line', 'url' => '/settings/severities', 'order' => 6],
            ['parent' => 'settings', 'name' => 'Systems', 'code' => 'system-settings', 'icon' => 'ri-computer-line', 'url' => '/settings/system', 'order' => 7],
            ['parent' => 'settings', 'name' => 'Departments', 'code' => 'department-settings', 'icon' => 'ri-community-line', 'url' => '/settings/department', 'order' => 8],
            ['parent' => 'settings', 'name' => 'Stations', 'code' => 'station-settings', 'icon' => 'ri-map-pin-range-line', 'url' => '/settings/station', 'order' => 9],
            ['parent' => 'settings', 'name' => 'Users', 'code' => 'user-settings', 'icon' => 'ri-user-settings-line', 'url' => '/settings/users', 'order' => 10],
            ['parent' => 'settings', 'name' => 'Roles', 'code' => 'role-settings', 'icon' => 'ri-shield-user-line', 'url' => '/settings/roles', 'order' => 11],
            ['parent' => 'settings', 'name' => 'Permissions', 'code' => 'permission-settings', 'icon' => 'ri-key-2-line', 'url' => '/settings/permissions', 'order' => 12],
            ['parent' => 'settings', 'name' => 'Team', 'code' => 'team', 'icon' => 'ri-team-line', 'url' => '/settings/team', 'order' => 13],
        ];

        foreach ($children as $menu) {
            $parentCode = $menu['parent'];
            unset($menu['parent']);
            Menu::create([
                ...$menu,
                'parent_id' => $menuIds[$parentCode],
                'status' => true,
                'is_side_menu' => true,
            ]);
        }
    }
}
