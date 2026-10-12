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
        Schema::create('tkt_system_configurations', function (Blueprint $table) {
            $table->id();

            $table->string('app_name')->default('Velzon Support Portal');
            $table->string('company_name')->default('Enterprise Global Inc.');
            $table->string('support_email')->default('support@enterprise.com');
            $table->string('hotline', 50)->default('+1 (800) 555-0199');
            $table->string('timezone', 64)->default('Asia/Singapore');
            $table->string('date_format', 32)->default('YYYY-MM-DD');
            $table->boolean('maintenance_mode')->default(false);

            $table->string('preset_color', 50)->default('default');
            $table->char('primary_color', 7)->default('#405189');
            $table->string('button_radius', 20)->default('rounded');
            $table->string('button_weight', 3)->default('600');
            $table->string('button_hover_effect', 20)->default('lift');
            $table->string('input_radius', 20)->default('rounded');
            $table->string('input_border_width', 10)->default('1px');
            $table->string('input_style', 20)->default('default');
            $table->string('input_focus_ring', 20)->default('primary');
            $table->string('checkbox_radius', 20)->default('subtle');
            $table->string('checkbox_size', 20)->default('default');
            $table->string('checkbox_border_width', 10)->default('1.5px');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tkt_system_configurations');
    }
};
