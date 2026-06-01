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
        Schema::create('point_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['PHYSICAL', 'VOUCHER']);
            $table->integer('stock')->default(0);
            $table->integer('point_cost');
            $table->enum('discount_type', ['PERCENTAGE', 'FIXED'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->integer('expiry_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_items');
    }
};
