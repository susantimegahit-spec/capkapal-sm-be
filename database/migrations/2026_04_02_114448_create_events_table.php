<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('banner')->nullable();           // S3 path
            $table->string('banner_mobile')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->enum('status', ['draft', 'active', 'ended', 'cancelled'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'start_at', 'end_at']); // query active events
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
