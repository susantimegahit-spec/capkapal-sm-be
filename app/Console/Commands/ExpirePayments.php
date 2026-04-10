<?php

namespace App\Console\Commands;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePayments extends Command
{
    protected $signature   = 'payments:expire';
    protected $description = 'Tandai payment yang sudah expired dan cancel order terkait';

    public function handle(): void
    {
        $expired = Payment::where('status', PaymentStatus::Waiting)
            ->where('expired_at', '<', now())
            ->with('order.items')
            ->get();

        $this->info("Memproses {$expired->count()} payment expired...");

        foreach ($expired as $payment) {
            DB::transaction(function () use ($payment) {
                $payment->update(['status' => PaymentStatus::Expired]);

                $order = $payment->order;

                if ($order && ! $order->status->isFinal()) {
                    // Kembalikan stok
                    foreach ($order->items as $item) {
                        \App\Modules\Product\Models\ProductVariant::where('id', $item->product_variant_id)
                            ->increment('stock', $item->quantity);
                    }

                    // Kembalikan poin jika ada
                    if ($order->points_used > 0) {
                        \App\Jobs\RefundRewardPoints::dispatch(
                            $order->user_id,
                            $order->points_used,
                            $order->id
                        )->onQueue('high');
                    }

                    $order->transitionTo(OrderStatus::Cancelled, 'Pembayaran kedaluwarsa.');
                }
            });
        }

        $this->info('Selesai.');
    }
}
