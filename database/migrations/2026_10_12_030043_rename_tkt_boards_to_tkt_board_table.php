<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('tkt_boards') && ! Schema::hasTable('tkt_board')) {
            Schema::rename('tkt_boards', 'tkt_board');
        } elseif (! Schema::hasTable('tkt_board')) {
            Schema::create('tkt_board', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('status')->default(true);
                $table->softDeletes();
                $table->unsignedBigInteger('created_by')->default(1);
                $table->unsignedBigInteger('updated_by')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tkt_board_status')) {
            Schema::create('tkt_board_status', function (Blueprint $table) {
                $table->id();
                $table->integer('order')->default(1);
                $table->string('name');
                $table->string('color')->default('primary');
                $table->string('type')->nullable();
                $table->tinyInteger('is_required')->default(0);
                $table->boolean('has_next_step')->default(true);
                $table->tinyInteger('notify_creator')->default(0);
                $table->tinyInteger('notify_followers')->default(0);
                $table->tinyInteger('notify_assignees')->default(0);
                $table->softDeletes();
                $table->unsignedBigInteger('board_id');
                $table->unsignedBigInteger('created_by')->default(1);
                $table->unsignedBigInteger('updated_by')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tkt_system_tickets')) {
            Schema::create('tkt_system_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('ref_no')->nullable();
                $table->string('subject')->nullable();
                $table->longText('summary')->nullable();
                $table->date('date_encountered')->nullable();
                $table->string('frequency', 20)->default('Once');
                $table->unsignedBigInteger('board_status_id')->nullable();
                $table->unsignedBigInteger('issue_type_id')->default(1);
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('system', 50)->nullable();
                $table->string('status', 20)->default('open');
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tkt_issue_types')) {
            Schema::create('tkt_issue_types', function (Blueprint $table) {
                $table->id('issue_id');
                $table->string('issue_desc', 50)->nullable()->unique();
                $table->string('color')->nullable();
                $table->timestamps();
            });
        }

        // Backward compatibility view
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS tkt_boards');
            DB::statement('CREATE VIEW tkt_boards AS SELECT * FROM tkt_board');
        } else {
            DB::statement('CREATE OR REPLACE VIEW `tkt_boards` AS SELECT * FROM `tkt_board`');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS tkt_boards');
        } else {
            DB::statement('DROP VIEW IF EXISTS `tkt_boards`');
        }

        if (Schema::hasTable('tkt_board') && ! Schema::hasTable('tkt_boards')) {
            Schema::rename('tkt_board', 'tkt_boards');
        }
    }
};
