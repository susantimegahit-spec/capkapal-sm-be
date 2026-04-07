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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('order_number', 30)->unique(); // GCK-20250601-00042
            $table->enum('status', [
                'pending',      // menunggu pembayaran
                'confirmed',    // pembayaran diterima
                'processing',   // sedang diproses/dikemas
                'shipped',      // sudah dikirim
                'delivered',    // sudah diterima
                'completed',    // selesai (review allowed)
                'cancelled',    // dibatalkan
                'refunded',     // direfund
            ])->default('pending');

            // Pricing breakdown
            $table->decimal('subtotal', 15, 2);          // total sebelum diskon & ongkir
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('coupon_discount', 15, 2)->default(0);
            $table->decimal('points_discount', 15, 2)->default(0);
            $table->decimal('shipping_fee', 15, 2)->default(0);
            $table->decimal('total', 15, 2);             // grand total

            // Coupon & points yang dipakai
            $table->string('coupon_code', 50)->nullable();
            $table->unsignedInteger('points_used')->default(0);
            $table->unsignedInteger('points_earned')->default(0);

            // Shipping
            $table->string('shipping_courier', 30)->nullable();  // jne, jnt, sicepat
            $table->string('shipping_service', 30)->nullable();  // reg, yes, oke
            $table->string('tracking_number', 50)->nullable();
            $table->date('estimated_delivery')->nullable();

            // Notes
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();

            // Timestamps
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('order_number');
            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
