<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tkt_issue_types') && ! Schema::hasColumn('tkt_issue_types', 'board_id')) {
            Schema::table('tkt_issue_types', function (Blueprint $table) {
                $table->unsignedBigInteger('board_id')->nullable()->after('issue_id');
                $table->index('board_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tkt_issue_types') && Schema::hasColumn('tkt_issue_types', 'board_id')) {
            Schema::table('tkt_issue_types', function (Blueprint $table) {
                $table->dropIndex(['board_id']);
                $table->dropColumn('board_id');
            });
        }
    }
};
