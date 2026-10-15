<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tkt_system_tickets', 'workflow_action')) {
            Schema::table('tkt_system_tickets', function (Blueprint $table) {
                $table->string('workflow_action', 50)->nullable()->after('group_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tkt_system_tickets', 'workflow_action')) {
            Schema::table('tkt_system_tickets', function (Blueprint $table) {
                $table->dropColumn('workflow_action');
            });
        }
    }
};
