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
        Schema::create('tkt_type', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->unsignedBigInteger('board_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        if (Schema::hasTable('tkt_system_tickets')) {
            Schema::table('tkt_system_tickets', function (Blueprint $table) {
                if (! Schema::hasColumn('tkt_system_tickets', 'tkt_type_id')) {
                    $table->unsignedBigInteger('tkt_type_id')->nullable()->after('issue_type_id')->index();
                }
                if (! Schema::hasColumn('tkt_system_tickets', 'type_other')) {
                    $table->string('type_other', 255)->nullable()->after('tkt_type_id');
                }
                $table->bigInteger('issue_type_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tkt_system_tickets')) {
            Schema::table('tkt_system_tickets', function (Blueprint $table) {
                if (Schema::hasColumn('tkt_system_tickets', 'type_other')) {
                    $table->dropColumn('type_other');
                }
                if (Schema::hasColumn('tkt_system_tickets', 'tkt_type_id')) {
                    $table->dropColumn('tkt_type_id');
                }
            });
        }

        Schema::dropIfExists('tkt_type');
    }
};
