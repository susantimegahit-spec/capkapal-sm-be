<?php
// create_reward_points_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();          // 1:1 dengan user
            $table->unsignedInteger('balance')->default(0);          // saldo aktif
            $table->unsignedInteger('lifetime_earned')->default(0);  // total sepanjang waktu (untuk tier)
            $table->unsignedInteger('lifetime_redeemed')->default(0);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index('balance');
            $table->index('lifetime_earned'); // untuk tier calculation
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_points');
    }
};
