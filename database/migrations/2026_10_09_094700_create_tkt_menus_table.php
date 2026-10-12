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
        if (! Schema::hasTable('tkt_menus')) {
            Schema::create('tkt_menus', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable()->default(0);
                $table->string('code')->unique();
                $table->string('name');
                $table->string('icon')->nullable();
                $table->string('url')->nullable();
                $table->integer('order')->default(0);
                $table->boolean('status')->default(true);
                $table->boolean('is_side_menu')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tkt_menus');
    }
};
