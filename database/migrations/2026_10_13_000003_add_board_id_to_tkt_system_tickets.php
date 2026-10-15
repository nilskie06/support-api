<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tkt_system_tickets', 'board_id')) {
            Schema::table('tkt_system_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('board_id')->nullable()->after('board_status_id');
                $table->index('board_id');
            });
        }

        DB::statement(
            'UPDATE tkt_system_tickets AS tickets
             INNER JOIN tkt_board_status AS statuses ON statuses.id = tickets.board_status_id
             SET tickets.board_id = statuses.board_id
             WHERE tickets.board_id IS NULL'
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('tkt_system_tickets', 'board_id')) {
            Schema::table('tkt_system_tickets', function (Blueprint $table) {
                $table->dropIndex(['board_id']);
                $table->dropColumn('board_id');
            });
        }
    }
};
