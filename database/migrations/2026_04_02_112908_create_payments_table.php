<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->unique(); // 1:1 per order (retry = update)
            $table->string('xendit_id')->unique()->nullable(); // Xendit external ID
            $table->string('xendit_payment_id')->nullable();   // ID dari response Xendit

            $table->enum('method', [
                'virtual_account',
                'ewallet',
                'qris',
                'credit_card',
                'retail_outlet',
            ]);

            $table->enum('provider', [
                // Virtual Account
                'bca', 'mandiri', 'bni', 'bri', 'permata', 'bsi',
                // E-Wallet
                'ovo', 'dana', 'gopay', 'shopeepay', 'linkaja',
                // Retail
                'alfamart', 'indomaret',
                // Other
                'qris', 'credit_card',
            ])->nullable();

            $table->enum('status', [
                'pending',    // menunggu bayar
                'waiting',    // instruksi sudah dikirim, menunggu konfirmasi
                'paid',       // sudah dibayar
                'failed',     // gagal
                'expired',    // kedaluwarsa
                'refunded',   // direfund
                'voided',     // dibatalkan sebelum bayar
            ])->default('pending');

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('IDR');

            // Instruksi pembayaran (VA number, QR code, dll.)
            $table->string('payment_code')->nullable();        // nomor VA / kode bayar
            $table->text('payment_url')->nullable();           // link ewallet / QR URL
            $table->text('qr_string')->nullable();             // QRIS string

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            // Metadata dari Xendit
            $table->jsonb('xendit_metadata')->nullable();

            $table->timestamps();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();

            $table->index(['status', 'expired_at']);    // cek expired payments
            $table->index('xendit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
