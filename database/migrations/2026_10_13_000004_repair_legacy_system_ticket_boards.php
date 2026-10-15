<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tickets created by the old form used SYS-* system codes but did not
        // persist the selected board. Those codes identify the System board.
        DB::table('tkt_system_tickets')
            ->where('system', 'like', 'SYS-%')
            ->where('board_id', '<>', 1)
            ->update(['board_id' => 1]);
    }

    public function down(): void
    {
        // The original board cannot be recovered because it was not stored.
    }
};
