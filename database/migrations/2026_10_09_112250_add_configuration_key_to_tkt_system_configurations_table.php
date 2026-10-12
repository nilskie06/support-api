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
            $table->string('configuration_key', 50)->unique()->default('default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tkt_system_configurations', function (Blueprint $table) {
            $table->dropUnique(['configuration_key']);
            $table->dropColumn('configuration_key');
        });
    }
};
