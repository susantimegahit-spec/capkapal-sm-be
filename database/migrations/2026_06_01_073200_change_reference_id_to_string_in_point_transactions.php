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
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->string('reference_id_new')->nullable()->after('reference_id');
        });

        // Copy existing data (cast bigint to string)
        DB::statement('UPDATE point_transactions SET reference_id_new = reference_id::text WHERE reference_id IS NOT NULL');

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropColumn('reference_id');
        });

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->renameColumn('reference_id_new', 'reference_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No safe reverse — keep as string
    }
};
