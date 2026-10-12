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
            $table->string('username')->nullable()->unique()->after('id');
            $table->string('fullname')->nullable()->after('name');
            $table->string('image')->nullable()->after('email');
            $table->foreignId('created_by')->nullable()->after('password')->constrained('users')->nullOnDelete();
            $table->string('status')->default('active')->after('created_by');
            $table->text('last_device_token')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('last_device_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'username',
                'fullname',
                'image',
                'status',
                'last_device_token',
                'last_login_at',
            ]);
        });
    }
};
