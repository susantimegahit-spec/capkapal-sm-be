<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('product_variant_id');

            // Snapshot data produk saat order dibuat (penting! harga bisa berubah)
            $table->string('product_name');           // snapshot
            $table->string('variant_name', 100);      // snapshot
            $table->string('sku', 50);                // snapshot
            $table->decimal('unit_price', 15, 2);     // harga saat order
            $table->unsignedSmallInteger('quantity');
            $table->decimal('subtotal', 15, 2);       // unit_price × quantity
            $table->decimal('weight', 8, 2)->nullable(); // gram, untuk kalkulasi ongkir
            $table->jsonb('product_snapshot')->nullable(); // full snapshot JSON produk
            $table->timestamps();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();

            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->restrictOnDelete();

            $table->index('order_id');
            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
