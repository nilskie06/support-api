<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('recipient_id');
            $table->index(['group_id', 'created_at']);
            $table->foreignId('recipient_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'created_at']);
            $table->dropColumn('group_id');
            $table->foreignId('recipient_id')->nullable(false)->change();
        });
    }
};
