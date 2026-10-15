<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tkt_board') && ! Schema::hasColumn('tkt_board', 'department_id')) {
            Schema::table('tkt_board', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable()->after('status');
                $table->index('department_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tkt_board') && Schema::hasColumn('tkt_board', 'department_id')) {
            Schema::table('tkt_board', function (Blueprint $table) {
                $table->dropIndex(['department_id']);
                $table->dropColumn('department_id');
            });
        }
    }
};
