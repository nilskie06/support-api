<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_boards_from_tkt_board(): void
    {
        $board = Board::create([
            'name' => 'Support Desk Workflow',
            'status' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        BoardStatus::create([
            'board_id' => $board->id,
            'name' => 'Open',
            'color' => 'primary',
            'type' => 'open',
            'order' => 1,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $response = $this->getJson('/api/boards');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'stats']);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Support Desk Workflow', $data[0]['name']);
        $this->assertCount(1, $data[0]['statuses']);
        $this->assertDatabaseHas('tkt_board', [
            'id' => $board->id,
            'name' => 'Support Desk Workflow',
        ]);
    }

    public function test_can_create_board_in_tkt_board(): void
    {
        $response = $this->postJson('/api/boards', [
            'name' => 'DevOps Pipeline',
            'status' => true,
            'statuses' => [
                ['name' => 'Backlog', 'color' => 'secondary', 'type' => 'open'],
                ['name' => 'In CI/CD', 'color' => 'warning', 'type' => 'progress'],
                ['name' => 'Production', 'color' => 'success', 'type' => 'closed'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'DevOps Pipeline');

        $this->assertDatabaseHas('tkt_board', [
            'name' => 'DevOps Pipeline',
            'status' => 1,
        ]);

        $boardId = $response->json('data.id');
        $this->assertDatabaseHas('tkt_board_status', [
            'board_id' => $boardId,
            'name' => 'Backlog',
        ]);
        $this->assertDatabaseHas('tkt_board_status', [
            'board_id' => $boardId,
            'name' => 'Production',
        ]);
    }

    public function test_can_update_board_in_tkt_board(): void
    {
        $board = Board::create([
            'name' => 'Initial Board',
            'status' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $response = $this->putJson("/api/boards/{$board->id}", [
            'name' => 'Renamed Board',
            'status' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Renamed Board')
            ->assertJsonPath('data.status', false);

        $this->assertDatabaseHas('tkt_board', [
            'id' => $board->id,
            'name' => 'Renamed Board',
            'status' => 0,
        ]);
    }

    public function test_can_delete_board_from_tkt_board(): void
    {
        $board = Board::create([
            'name' => 'Temporary Board',
            'status' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        BoardStatus::create([
            'board_id' => $board->id,
            'name' => 'Draft Stage',
            'color' => 'info',
            'type' => 'open',
            'order' => 1,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $response = $this->deleteJson("/api/boards/{$board->id}");

        $response->assertStatus(200);

        // Soft deleted in tkt_board
        $this->assertSoftDeleted('tkt_board', [
            'id' => $board->id,
        ]);
    }

    public function test_can_manage_board_statuses(): void
    {
        $board = Board::create([
            'name' => 'Stage Test Board',
            'status' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        // Add stage
        $createStatusRes = $this->postJson("/api/boards/{$board->id}/statuses", [
            'name' => 'Investigation',
            'color' => 'info',
            'type' => 'open',
            'has_next_step' => true,
            'notify_creator' => true,
        ]);

        $createStatusRes->assertStatus(201);
        $statusId = $createStatusRes->json('data.id');

        $this->assertDatabaseHas('tkt_board_status', [
            'id' => $statusId,
            'board_id' => $board->id,
            'name' => 'Investigation',
            'notify_creator' => 1,
        ]);

        // Update stage
        $updateStatusRes = $this->putJson("/api/boards/{$board->id}/statuses/{$statusId}", [
            'name' => 'Deep Dive Investigation',
            'color' => 'danger',
            'type' => 'progress',
        ]);

        $updateStatusRes->assertStatus(200);

        $this->assertDatabaseHas('tkt_board_status', [
            'id' => $statusId,
            'name' => 'Deep Dive Investigation',
            'color' => 'danger',
        ]);

        // Delete stage
        $deleteStatusRes = $this->deleteJson("/api/boards/{$board->id}/statuses/{$statusId}");
        $deleteStatusRes->assertStatus(200);

        $this->assertSoftDeleted('tkt_board_status', [
            'id' => $statusId,
        ]);
    }
}
