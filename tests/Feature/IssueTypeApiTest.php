<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardStatus;
use App\Models\IssueType;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueTypeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_issue_types_from_tkt_issue_types(): void
    {
        $type = IssueType::create([
            'issue_desc' => 'Technical Debt',
            'color' => '#8e44ad',
        ]);

        $response = $this->getJson('/api/issue-types');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'stats']);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Technical Debt', $data[0]['issue_desc']);
        $this->assertEquals('#8e44ad', $data[0]['color']);
        $this->assertEquals(0, $data[0]['tickets_count']);
    }

    public function test_can_create_issue_type(): void
    {
        $response = $this->postJson('/api/issue-types', [
            'issue_desc' => 'Data Integrity',
            'color' => '#27ae60',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.issue_desc', 'Data Integrity')
            ->assertJsonPath('data.color', '#27ae60');

        $this->assertDatabaseHas('tkt_issue_types', [
            'issue_desc' => 'Data Integrity',
            'color' => '#27ae60',
        ]);
    }

    public function test_validates_required_fields_and_uniqueness_on_create(): void
    {
        IssueType::create([
            'issue_desc' => 'Security Audit',
            'color' => '#c0392b',
        ]);

        // Duplicate
        $response = $this->postJson('/api/issue-types', [
            'issue_desc' => 'Security Audit',
            'color' => '#c0392b',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['issue_desc']);

        // Missing description
        $responseEmpty = $this->postJson('/api/issue-types', [
            'issue_desc' => '',
        ]);

        $responseEmpty->assertStatus(422)
            ->assertJsonValidationErrors(['issue_desc']);
    }

    public function test_can_show_issue_type(): void
    {
        $type = IssueType::create([
            'issue_desc' => 'Infrastructure',
            'color' => '#2980b9',
        ]);

        $response = $this->getJson("/api/issue-types/{$type->issue_id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.issue_id', $type->issue_id)
            ->assertJsonPath('data.issue_desc', 'Infrastructure');
    }

    public function test_can_update_issue_type(): void
    {
        $type = IssueType::create([
            'issue_desc' => 'Old Description',
            'color' => '#34495e',
        ]);

        $response = $this->putJson("/api/issue-types/{$type->issue_id}", [
            'issue_desc' => 'Updated Description',
            'color' => '#16a085',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.issue_desc', 'Updated Description')
            ->assertJsonPath('data.color', '#16a085');

        $this->assertDatabaseHas('tkt_issue_types', [
            'issue_id' => $type->issue_id,
            'issue_desc' => 'Updated Description',
            'color' => '#16a085',
        ]);
    }

    public function test_can_delete_issue_type_when_no_tickets_linked(): void
    {
        $type = IssueType::create([
            'issue_desc' => 'Temporary Type',
            'color' => '#95a5a6',
        ]);

        $response = $this->deleteJson("/api/issue-types/{$type->issue_id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('tkt_issue_types', [
            'issue_id' => $type->issue_id,
        ]);
    }

    public function test_cannot_delete_issue_type_when_tickets_are_linked(): void
    {
        $type = IssueType::create([
            'issue_desc' => 'Protected Type',
            'color' => '#e74c3c',
        ]);

        $board = Board::create([
            'name' => 'Main Board',
            'status' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $status = BoardStatus::create([
            'board_id' => $board->id,
            'name' => 'Open',
            'color' => 'primary',
            'type' => 'open',
            'order' => 1,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        Ticket::create([
            'ref_no' => 'TKT-TEST-001',
            'subject' => 'Issue linked to type',
            'summary' => 'Test ticket summary',
            'date_encountered' => now()->toDateString(),
            'frequency' => 'Always',
            'board_status_id' => $status->id,
            'issue_type_id' => $type->issue_id,
        ]);

        $response = $this->deleteJson("/api/issue-types/{$type->issue_id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete this issue type because it is currently assigned to existing tickets.');

        $this->assertDatabaseHas('tkt_issue_types', [
            'issue_id' => $type->issue_id,
        ]);
    }
}
