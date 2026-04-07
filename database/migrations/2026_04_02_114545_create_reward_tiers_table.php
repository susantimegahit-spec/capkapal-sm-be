<?php
// create_reward_tiers_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50);           // Bronze, Silver, Gold, Platinum
            $table->unsignedInteger('min_points'); // lifetime points threshold
            $table->decimal('discount_pct', 5, 2)->default(0); // % diskon member tier
            $table->unsignedSmallInteger('points_multiplier')->default(1); // poin × multiplier per transaksi
            $table->jsonb('perks')->nullable();    // ["free_shipping","priority_support"]
            $table->string('badge_color', 20)->nullable();
            $table->string('badge_icon')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('min_points');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_tiers');
    }
};
