<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('xendit_id', 'midtrans_order_id');
            $table->renameColumn('xendit_payment_id', 'midtrans_transaction_id');
            $table->renameColumn('xendit_metadata', 'gateway_metadata');
        });

        Schema::table('payment_logs', function (Blueprint $table) {
            $table->renameColumn('xendit_event_id', 'notification_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('midtrans_order_id', 'xendit_id');
            $table->renameColumn('midtrans_transaction_id', 'xendit_payment_id');
            $table->renameColumn('gateway_metadata', 'xendit_metadata');
        });

        Schema::table('payment_logs', function (Blueprint $table) {
            $table->renameColumn('notification_id', 'xendit_event_id');
        });
    }
};
