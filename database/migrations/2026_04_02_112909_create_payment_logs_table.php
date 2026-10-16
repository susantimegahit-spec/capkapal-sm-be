<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id')->nullable();           // nullable: log bisa diterima sebelum payment dibuat
            $table->string('xendit_event_id')->unique();      // idempotency key dari Xendit
            $table->string('event_type', 60);                 // 'payment.succeeded', 'payment.failed', dll.
            $table->jsonb('payload');                         // raw webhook payload
            $table->string('status', 30)->default('received'); // received | processed | failed
            $table->text('error_message')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();

            // Tidak pakai $table->timestamps() — pakai received_at/processed_at manual

            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->nullOnDelete();

            $table->index('payment_id');
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
