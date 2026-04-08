<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the table and all columns/indexes first
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'is_active']);
            $table->index('sort_order');
            $table->index('slug');
        });

        // 2. Add the self-referencing foreign key in a separate block
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // 1. Drop the foreign key first to prevent constraint violations on rollback
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        // 2. Drop the table
        Schema::dropIfExists('categories');
    }
};
