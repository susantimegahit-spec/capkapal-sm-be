<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('promo_id')->nullable();
            $table->string('code', 50)->unique();           // PROMO2025, WELCOME10
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->decimal('min_purchase', 15, 2)->default(0);
            $table->unsignedInteger('max_uses')->nullable();     // null = unlimited
            $table->unsignedInteger('max_uses_per_user')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->enum('user_type_restriction', ['all', 'consumer', 'umkm', 'distributor'])->default('all');
            $table->uuid('specific_user_id')->nullable();       // coupon personal
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('promo_id')
                ->references('id')
                ->on('promos')
                ->nullOnDelete();

            $table->index(['code', 'is_active']);
            $table->index(['expires_at', 'is_active']);
            $table->index('specific_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
