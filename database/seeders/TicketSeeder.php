<?php

namespace Database\Seeders;

use App\Models\BoardStatus;
use App\Models\IssueSeverity;
use App\Models\IssueType;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Board Statuses (tkt_board_status)
        $this->call(BoardStatusSeeder::class);

        // 2. Issue Severities
        $severities = [
            ['name' => 'Low', 'color' => 'success', 'enable' => true],
            ['name' => 'Medium', 'color' => 'warning', 'enable' => true],
            ['name' => 'High', 'color' => 'danger', 'enable' => true],
            ['name' => 'Critical', 'color' => 'dark', 'enable' => true],
        ];

        foreach ($severities as $sev) {
            IssueSeverity::updateOrCreate(
                ['name' => $sev['name']],
                [
                    'color' => $sev['color'],
                    'enable' => $sev['enable'],
                ]
            );
        }

        // 3. Issue Types
        $types = [
            ['issue_desc' => 'Bug / Defect', 'color' => '#f06548'],
            ['issue_desc' => 'Feature Request', 'color' => '#405189'],
            ['issue_desc' => 'UI / UX Improvement', 'color' => '#299cdb'],
            ['issue_desc' => 'Performance Issue', 'color' => '#ffbe0c'],
            ['issue_desc' => 'Security Vulnerability', 'color' => '#d03b3b'],
            ['issue_desc' => 'General Inquiry', 'color' => '#0ab39c'],
        ];

        foreach ($types as $type) {
            IssueType::updateOrCreate(
                ['issue_desc' => $type['issue_desc']],
                ['color' => $type['color']]
            );
        }

        // 4. Sample Users for Assignees
        $sampleUsers = [
            [
                'name' => 'Alexis Clarke',
                'username' => 'alexis.clarke',
                'fullname' => 'Alexis Clarke',
                'email' => 'alexis@example.com',
                'image' => 'avatar-1.jpg',
            ],
            [
                'name' => 'James Forbes',
                'username' => 'james.forbes',
                'fullname' => 'James Forbes',
                'email' => 'james@example.com',
                'image' => 'avatar-2.jpg',
            ],
            [
                'name' => 'Donald Palmer',
                'username' => 'donald.palmer',
                'fullname' => 'Donald Palmer',
                'email' => 'donald@example.com',
                'image' => 'avatar-3.jpg',
            ],
            [
                'name' => 'Tommy Carey',
                'username' => 'tommy.carey',
                'fullname' => 'Tommy Carey',
                'email' => 'tommy@example.com',
                'image' => 'avatar-4.jpg',
            ],
        ];

        foreach ($sampleUsers as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'username' => $u['username'],
                    'fullname' => $u['fullname'],
                    'image' => $u['image'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
        }

        // 5. Sample Tickets in tkt_system_tickets if empty
        if (Ticket::count() === 0) {
            $newStatus = BoardStatus::where('name', 'OPEN')->first() ?? BoardStatus::first();
            $openStatus = BoardStatus::where('name', 'ASSIGNED')->first() ?? $newStatus;
            $inprogressStatus = BoardStatus::where('name', 'IN PROGRESS')->first() ?? $openStatus;
            $closedStatus = BoardStatus::where('name', 'CLOSE')->first() ?? $inprogressStatus;

            $lowSev = IssueSeverity::where('name', 'Low')->first();
            $medSev = IssueSeverity::where('name', 'Medium')->first();
            $highSev = IssueSeverity::where('name', 'High')->first();
            $critSev = IssueSeverity::where('name', 'Critical')->first();

            $bugType = IssueType::where('issue_desc', 'Bug / Defect')->first();
            $featType = IssueType::where('issue_desc', 'Feature Request')->first();
            $uiType = IssueType::where('issue_desc', 'UI / UX Improvement')->first();
            $perfType = IssueType::where('issue_desc', 'Performance Issue')->first();

            $users = User::all();
            $adminUser = User::where('email', 'admin@example.com')->first() ?? $users->first();

            $sampleTickets = [
                [
                    'ref_no' => 'TKT-2026-0001',
                    'subject' => 'Sidebar menu collapses unexpectedly on tablet screen',
                    'summary' => 'When resizing viewport between 768px and 992px, the left sidebar toggles closed and cannot be reopened with top hamburger button.',
                    'date_encountered' => Carbon::now()->subDays(6)->toDateString(),
                    'duration_from' => Carbon::now()->subDays(6)->toDateString(),
                    'duration_to' => Carbon::now()->addDays(2)->toDateString(),
                    'frequency' => 'Always',
                    'system' => 'Web',
                    'module' => 'menu_management',
                    'board_status_id' => $openStatus->id,
                    'issue_severity_id' => $highSev->id,
                    'issue_type_id' => $bugType->issue_id,
                    'status' => 1,
                    'assignee_ids' => [$users[1]->id ?? 1],
                ],
                [
                    'ref_no' => 'TKT-2026-0002',
                    'subject' => 'Add export to CSV and Excel options in ticket tables',
                    'summary' => 'Support agents need an easy way to export filtered ticket records with assignee metadata into Excel and CSV spreadsheets for weekly audits.',
                    'date_encountered' => Carbon::now()->subDays(5)->toDateString(),
                    'duration_from' => Carbon::now()->subDays(5)->toDateString(),
                    'duration_to' => Carbon::now()->addDays(5)->toDateString(),
                    'frequency' => 'Once',
                    'system' => 'Web',
                    'module' => 'tickets',
                    'board_status_id' => $inprogressStatus->id,
                    'issue_severity_id' => $medSev->id,
                    'issue_type_id' => $featType->issue_id,
                    'status' => 1,
                    'assignee_ids' => [$users[2]->id ?? 1, $users[0]->id ?? 1],
                ],
                [
                    'ref_no' => 'TKT-2026-0003',
                    'subject' => 'Slow response time during bulk ticket deletion operations',
                    'summary' => 'Database query execution takes over 3.8s when deleting 20+ ticket records concurrently because related assignees table lacked an index.',
                    'date_encountered' => Carbon::now()->subDays(4)->toDateString(),
                    'duration_from' => Carbon::now()->subDays(4)->toDateString(),
                    'duration_to' => Carbon::now()->addDays(1)->toDateString(),
                    'frequency' => 'Daily',
                    'system' => 'API',
                    'module' => 'tickets',
                    'board_status_id' => $openStatus->id,
                    'issue_severity_id' => $critSev->id,
                    'issue_type_id' => $perfType->issue_id,
                    'status' => 1,
                    'assignee_ids' => [$users[3]->id ?? 1],
                ],
                [
                    'ref_no' => 'TKT-2026-0004',
                    'subject' => 'Dark mode theme color contrast in ticket status badges',
                    'summary' => 'Warning and info badges have poor contrast when switching the Velzon layout to data-layout-mode="dark". Need adjusted soft background tokens.',
                    'date_encountered' => Carbon::now()->subDays(3)->toDateString(),
                    'duration_from' => Carbon::now()->subDays(3)->toDateString(),
                    'duration_to' => Carbon::now()->addDays(4)->toDateString(),
                    'frequency' => 'Always',
                    'system' => 'Web',
                    'module' => 'dashboard_crm',
                    'board_status_id' => $newStatus->id,
                    'issue_severity_id' => $lowSev->id,
                    'issue_type_id' => $uiType->issue_id,
                    'status' => 1,
                    'assignee_ids' => [$users[1]->id ?? 1],
                ],
                [
                    'ref_no' => 'TKT-2026-0005',
                    'subject' => 'Session expired redirection loop on ticket detail route',
                    'summary' => 'When JWT token expires while browsing /apps/tickets-details, user gets caught in a loop between login route and router beforeResolve handler.',
                    'date_encountered' => Carbon::now()->subDays(2)->toDateString(),
                    'duration_from' => Carbon::now()->subDays(2)->toDateString(),
                    'duration_to' => Carbon::now()->subDays(1)->toDateString(),
                    'frequency' => 'Random',
                    'system' => 'Web',
                    'module' => 'tickets',
                    'board_status_id' => $closedStatus->id,
                    'issue_severity_id' => $highSev->id,
                    'issue_type_id' => $bugType->issue_id,
                    'status' => 0,
                    'assignee_ids' => [$users[0]->id ?? 1],
                ],
                [
                    'ref_no' => 'TKT-2026-0006',
                    'subject' => 'Email notification template formatting error on closed ticket',
                    'summary' => 'HTML tags in the resolution comments are escaping incorrectly in automated client notification emails.',
                    'date_encountered' => Carbon::now()->subDays(1)->toDateString(),
                    'duration_from' => Carbon::now()->subDays(1)->toDateString(),
                    'duration_to' => Carbon::now()->addDays(3)->toDateString(),
                    'frequency' => 'Daily',
                    'system' => 'API',
                    'module' => 'settings_general',
                    'board_status_id' => $inprogressStatus->id,
                    'issue_severity_id' => $medSev->id,
                    'issue_type_id' => $bugType->issue_id,
                    'status' => 1,
                    'assignee_ids' => [$users[2]->id ?? 1],
                ],
                [
                    'ref_no' => 'TKT-2026-0007',
                    'subject' => 'Enable drag-and-drop file attachments in ticket creation form',
                    'summary' => 'Provide a dropzone widget so users can quickly drag error screenshots and crash logs directly into the ticket description.',
                    'date_encountered' => Carbon::now()->toDateString(),
                    'duration_from' => Carbon::now()->toDateString(),
                    'duration_to' => Carbon::now()->addDays(7)->toDateString(),
                    'frequency' => 'Once',
                    'system' => 'Web',
                    'module' => 'tickets',
                    'board_status_id' => $newStatus->id,
                    'issue_severity_id' => $lowSev->id,
                    'issue_type_id' => $featType->issue_id,
                    'status' => 1,
                    'assignee_ids' => [$users[3]->id ?? 1],
                ],
                [
                    'ref_no' => 'TKT-2026-0008',
                    'subject' => 'Invalid date filter parsing when range end date is empty',
                    'summary' => 'When user picks single date in flatpickr with range mode, string split causes NaN date calculation on resultQuery filter.',
                    'date_encountered' => Carbon::now()->toDateString(),
                    'duration_from' => Carbon::now()->toDateString(),
                    'duration_to' => Carbon::now()->subDays(1)->toDateString(),
                    'frequency' => 'Always',
                    'system' => 'Web',
                    'module' => 'tickets',
                    'board_status_id' => $closedStatus->id,
                    'issue_severity_id' => $medSev->id,
                    'issue_type_id' => $bugType->issue_id,
                    'status' => 0,
                    'assignee_ids' => [$users[1]->id ?? 1],
                ],
            ];

            foreach ($sampleTickets as $st) {
                $assignees = $st['assignee_ids'];
                unset($st['assignee_ids']);

                $ticket = Ticket::create(array_merge($st, [
                    'created_by' => $adminUser->id,
                    'updated_by' => $adminUser->id,
                ]));

                $ticket->assignees()->sync($assignees);
            }
        }
    }
}
