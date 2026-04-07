<?php
// create_user_tiers_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique(); // 1:1, satu user 1 tier aktif
            $table->uuid('tier_id');
            $table->timestamp('achieved_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('tier_id')->references('id')->on('reward_tiers')->restrictOnDelete();

            $table->index('tier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tiers');
    }
};
