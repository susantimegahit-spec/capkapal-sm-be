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
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('point_item_id')->constrained()->onDelete('cascade');
            $table->integer('points_used');
            $table->enum('type', ['PHYSICAL', 'VOUCHER']);
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'SHIPPED', 'DELIVERED', 'COMPLETED'])->default('PENDING');
            $table->text('shipping_address')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};
