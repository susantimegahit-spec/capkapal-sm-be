<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['flash_sale', 'bundle', 'seasonal', 'loyalty', 'new_user']);
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 10, 2);       // % atau nominal IDR
            $table->decimal('max_discount', 15, 2)->nullable(); // cap untuk promo %
            $table->decimal('min_purchase', 15, 2)->default(0); // minimal belanja
            $table->enum('applies_to', ['all', 'category', 'product'])->default('all');
            $table->jsonb('applicable_ids')->nullable();     // category/product UUIDs
            $table->enum('user_type_restriction', ['all', 'consumer', 'umkm', 'distributor'])->default('all');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->nullOnDelete();

            $table->index(['is_active', 'start_at', 'end_at']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promos');
    }
};
