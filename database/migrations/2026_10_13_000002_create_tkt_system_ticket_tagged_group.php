<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tkt_system_ticket_tagged_group')) {
            Schema::create('tkt_system_ticket_tagged_group', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('system_ticket_id');
                $table->unsignedBigInteger('group_id');
                $table->unique(['system_ticket_id', 'group_id']);
                $table->index('system_ticket_id');
                $table->index('group_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tkt_system_ticket_tagged_group');
    }
};
