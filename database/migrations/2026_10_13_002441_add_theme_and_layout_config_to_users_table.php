<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'theme_config')) {
                $table->json('theme_config')->nullable()->after('last_login_at');
            }
            if (! Schema::hasColumn('users', 'layout_config')) {
                $table->json('layout_config')->nullable()->after('theme_config');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'theme_config')) {
                $table->dropColumn('theme_config');
            }
            if (Schema::hasColumn('users', 'layout_config')) {
                $table->dropColumn('layout_config');
            }
        });
    }
};
