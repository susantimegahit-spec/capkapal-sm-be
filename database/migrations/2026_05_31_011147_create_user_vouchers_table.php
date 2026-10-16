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
        Schema::create('user_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('redemption_id')->constrained('redemptions')->onDelete('cascade');
            $table->string('code')->unique();
            $table->enum('discount_type', ['PERCENTAGE', 'FIXED']);
            $table->decimal('discount_value', 10, 2);
            $table->enum('status', ['ACTIVE', 'USED', 'EXPIRED'])->default('ACTIVE');
            $table->dateTime('valid_until');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_vouchers');
    }
};
