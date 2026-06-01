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
        Schema::table('point_items', function (Blueprint $table) {
            $table->enum('eligible_for', ['all', 'user', 'agen'])->default('all')->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_items', function (Blueprint $table) {
            $table->dropColumn('eligible_for');
        });
    }
};
