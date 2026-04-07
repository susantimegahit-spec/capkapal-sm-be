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
        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();        // null = guest cart
            $table->string('session_id', 100)->nullable(); // untuk guest
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('coupon_discount', 15, 2)->default(0);
            $table->unsignedInteger('points_used')->default(0);
            $table->decimal('points_discount', 15, 2)->default(0);
            $table->timestamp('expires_at')->nullable(); // guest cart TTL
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            // Satu user hanya punya 1 active cart
            $table->unique('user_id');

            $table->index('session_id');
            $table->index('expires_at'); // untuk pruning job
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
