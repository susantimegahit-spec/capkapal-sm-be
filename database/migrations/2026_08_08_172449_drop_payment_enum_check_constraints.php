<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop existing PostgreSQL ENUM check constraints for payments table
        // These constraints were created automatically by Laravel when creating ENUM columns.
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_method_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_provider_check');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Adding the check constraints back (this depends on the original values, so we put a generic one)
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method::text = ANY (ARRAY['virtual_account'::character varying, 'ewallet'::character varying, 'qris'::character varying, 'credit_card'::character varying, 'retail_outlet'::character varying, 'flat'::character varying, 'manual_qris'::character varying]::text[]))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status::text = ANY (ARRAY['pending'::character varying, 'waiting'::character varying, 'awaiting_verification'::character varying, 'paid'::character varying, 'failed'::character varying, 'rejected'::character varying, 'expired'::character varying, 'refunded'::character varying, 'voided'::character varying]::text[]))");
        // provider check is omitted to avoid errors since it might not be strictly defined
    }
};
