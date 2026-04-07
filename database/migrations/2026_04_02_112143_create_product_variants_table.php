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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('name', 100);   // e.g. "250g", "500g", "1kg"
            $table->string('sku', 50)->unique();
            $table->decimal('price', 15, 2);
            $table->decimal('price_distributor', 15, 2)->nullable();
            $table->decimal('price_umkm', 15, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(10);
            $table->decimal('weight', 8, 2)->nullable(); // dalam gram
            $table->jsonb('attributes')->nullable();     // {"berat":"250g","kemasan":"plastik"}
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->index(['product_id', 'is_active']);
            $table->index(['product_id', 'sort_order']);
            $table->index('stock'); // untuk low-stock alerts
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
