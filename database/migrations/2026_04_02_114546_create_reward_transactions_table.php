<?php
// create_reward_transactions_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->enum('type', [
                'earn',       // dari pembelian
                'redeem',     // digunakan untuk diskon
                'referral',   // bonus referral
                'campaign',   // bonus campaign/event
                'expire',     // poin kedaluwarsa (negative)
                'adjustment', // koreksi manual oleh admin
                'refund',     // poin dikembalikan karena order dibatalkan
            ]);
            $table->integer('points');              // positif = masuk, negatif = keluar
            $table->unsignedInteger('balance_after'); // saldo setelah transaksi
            $table->string('ref_type', 50)->nullable();  // 'order', 'campaign', dll.
            $table->uuid('ref_id')->nullable();           // order_id, campaign_id, dll.
            $table->text('description')->nullable();
            $table->timestamp('expires_at')->nullable();  // untuk poin yang akan expire
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
            $table->index(['expires_at', 'type']); // untuk expire job
            $table->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_transactions');
    }
};
