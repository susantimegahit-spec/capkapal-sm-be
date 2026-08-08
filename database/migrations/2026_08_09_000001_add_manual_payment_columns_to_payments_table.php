<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        // ── PostgreSQL: convert enum columns to varchar for flexibility ───────
        // PostgreSQL tidak punya ENUM yang mudah di-extend seperti MySQL.
        // Pendekatan paling aman: ubah ke VARCHAR.

        // 1. Method: enum → varchar
        DB::statement("ALTER TABLE payments ALTER COLUMN method TYPE VARCHAR(30) USING method::VARCHAR");

        // 2. Provider: enum → varchar
        DB::statement("ALTER TABLE payments ALTER COLUMN provider TYPE VARCHAR(30) USING provider::VARCHAR");

        // 3. Status: enum → varchar
        DB::statement("ALTER TABLE payments ALTER COLUMN status TYPE VARCHAR(30) USING status::VARCHAR");

        // 4. Rename kolom Xendit ke nama generik (jika masih ada kolom xendit)
        if (Schema::hasColumn('payments', 'xendit_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('xendit_id', 'midtrans_order_id');
            });
        }

        if (Schema::hasColumn('payments', 'xendit_payment_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('xendit_payment_id', 'midtrans_transaction_id');
            });
        }

        if (Schema::hasColumn('payments', 'xendit_metadata')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('xendit_metadata', 'gateway_metadata');
            });
        }

        // 5. Tambah kolom baru untuk manual payment
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_proof_path')->nullable()->after('qr_string');
            $table->unsignedSmallInteger('unique_code')->nullable()->after('payment_proof_path');
            $table->text('rejection_reason')->nullable()->after('unique_code');
            $table->uuid('verified_by')->nullable()->after('rejection_reason');
            $table->timestamp('verified_at')->nullable()->after('verified_by');

            $table->foreign('verified_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'payment_proof_path',
                'unique_code',
                'rejection_reason',
                'verified_by',
                'verified_at',
            ]);
        });

        // Revert kolom rename jika perlu
        if (Schema::hasColumn('payments', 'midtrans_order_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('midtrans_order_id', 'xendit_id');
            });
        }

        if (Schema::hasColumn('payments', 'midtrans_transaction_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('midtrans_transaction_id', 'xendit_payment_id');
            });
        }

        if (Schema::hasColumn('payments', 'gateway_metadata')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('gateway_metadata', 'xendit_metadata');
            });
        }
    }
};
