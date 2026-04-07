<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('coupon_id');
            $table->uuid('user_id');
            $table->uuid('order_id');
            $table->decimal('discount_applied', 15, 2);
            $table->timestamp('applied_at');

            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();

            // Satu coupon hanya bisa dipakai sekali per order
            $table->unique(['coupon_id', 'order_id']);

            $table->index(['coupon_id', 'user_id']); // cek max_uses_per_user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
