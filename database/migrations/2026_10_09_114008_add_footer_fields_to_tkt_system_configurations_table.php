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
            $table->string('footer_copyright_text')
                ->default('{year} © {appName}.');
            $table->string('footer_credit_text')
                ->default('Design & Develop by {companyName}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tkt_system_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'footer_copyright_text',
                'footer_credit_text',
            ]);
        });
    }
};
