<?php

namespace Tests\Feature;

use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_menus_in_tree_format(): void
    {
        $parent = Menu::create([
            'parent_id' => 0,
            'name' => 'Main',
            'code' => 'main',
            'order' => 1,
        ]);

        $child = Menu::create([
            'parent_id' => $parent->id,
            'name' => 'Sub',
            'code' => 'sub',
            'order' => 1,
        ]);

        $response = $this->getJson('/api/menus');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'flat']);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('main', $data[0]['code']);
        $this->assertCount(1, $data[0]['all_children']);
        $this->assertEquals('sub', $data[0]['all_children'][0]['code']);
    }

    public function test_can_create_menu_item(): void
    {
        $response = $this->postJson('/api/menus', [
            'name' => 'Reports',
            'code' => 'reports',
            'icon' => 'ri-file-chart-line',
            'url' => '/reports',
            'status' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'reports');

        $this->assertDatabaseHas('tkt_menus', [
            'code' => 'reports',
            'name' => 'Reports',
        ]);
    }

    public function test_can_update_menu_item(): void
    {
        $menu = Menu::create([
            'parent_id' => 0,
            'name' => 'Old Name',
            'code' => 'old_code',
        ]);

        $response = $this->putJson("/api/menus/{$menu->id}", [
            'name' => 'New Name',
            'code' => 'new_code',
            'url' => '/new-url',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('tkt_menus', [
            'id' => $menu->id,
            'name' => 'New Name',
            'code' => 'new_code',
        ]);
    }

    public function test_can_delete_menu_and_children(): void
    {
        $parent = Menu::create([
            'parent_id' => 0,
            'name' => 'Parent',
            'code' => 'parent',
        ]);

        $child = Menu::create([
            'parent_id' => $parent->id,
            'name' => 'Child',
            'code' => 'child',
        ]);

        $response = $this->deleteJson("/api/menus/{$parent->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('tkt_menus', ['id' => $parent->id]);
        $this->assertDatabaseMissing('tkt_menus', ['id' => $child->id]);
    }

    public function test_can_bulk_reorder_menus(): void
    {
        $m1 = Menu::create(['parent_id' => 0, 'name' => 'M1', 'code' => 'm1', 'order' => 1]);
        $m2 = Menu::create(['parent_id' => 0, 'name' => 'M2', 'code' => 'm2', 'order' => 2]);

        $payload = [
            'items' => [
                [
                    'id' => $m2->id,
                    'all_children' => [
                        ['id' => $m1->id],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/menus/reorder', $payload);

        $response->assertStatus(200);

        $this->assertEquals(0, $m2->fresh()->parent_id);
        $this->assertEquals(0, $m2->fresh()->order);
        $this->assertEquals($m2->id, $m1->fresh()->parent_id);
        $this->assertEquals(0, $m1->fresh()->order);
    }
}
