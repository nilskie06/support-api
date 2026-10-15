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
        Schema::table('tkt_system_configurations', function (Blueprint $table) {
            $table->longText('system_logo')->nullable()->after('app_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tkt_system_configurations', function (Blueprint $table) {
            $table->dropColumn('system_logo');
        });
    }
};
