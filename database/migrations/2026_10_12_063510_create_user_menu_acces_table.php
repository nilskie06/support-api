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
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE tkt_menus ENGINE=InnoDB');
            } catch (Throwable $e) {
                // ignore if already innodb
            }
        }

        if (! Schema::hasTable('user_menu_acces')) {
            Schema::create('user_menu_acces', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('menu_id')->index();
                $table->boolean('can_view')->default(false);
                $table->boolean('can_add')->default(false);
                $table->boolean('can_edit')->default(false);
                $table->boolean('can_delete')->default(false);
                $table->timestamps();

                $table->unique(['user_id', 'menu_id']);
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('menu_id')->references('id')->on('tkt_menus')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_menu_acces');
    }
};
