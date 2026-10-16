<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('career_id')->constrained('careers')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('resume_path');
            $table->text('cover_letter')->nullable();
            $table->string('status')->default('pending'); // pending, reviewed, accepted, rejected
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index('career_id');
            $table->index('status');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_applications');
    }
};
