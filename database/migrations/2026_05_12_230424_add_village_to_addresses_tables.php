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
        if (!Schema::hasColumn('user_addresses', 'village')) {
            Schema::table('user_addresses', function (Blueprint $table) {
                $table->string('village')->nullable()->after('district');
            });
        }

        if (!Schema::hasColumn('shipping_addresses', 'village')) {
            Schema::table('shipping_addresses', function (Blueprint $table) {
                $table->string('village')->nullable()->after('district');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('user_addresses', 'village')) {
            Schema::table('user_addresses', function (Blueprint $table) {
                $table->dropColumn('village');
            });
        }

        if (Schema::hasColumn('shipping_addresses', 'village')) {
            Schema::table('shipping_addresses', function (Blueprint $table) {
                $table->dropColumn('village');
            });
        }
    }
};
