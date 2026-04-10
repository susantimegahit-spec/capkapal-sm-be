<?php

namespace App\Modules\Payment\Jobs;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\{PaymentFailed, PaymentSucceeded};
use App\Modules\Payment\Models\{Payment, PaymentLog};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{DB, Log};

class ProcessXenditWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries    = 3;
    public int    $backoff  = 10; // detik antar retry

    public function __construct(private readonly string $logId) {}

    public function handle(): void
    {
        $log = PaymentLog::findOrFail($this->logId);

        // Double-check idempotency: jangan proses ulang yang sudah selesai
        if ($log->status === 'processed') {
            return;
        }

        DB::transaction(function () use ($log) {
            try {
                $this->process($log);
                $log->update(['status' => 'processed', 'processed_at' => now()]);
            } catch (\Throwable $e) {
                $log->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'processed_at'  => now(),
                ]);

                Log::error('Webhook processing failed', [
                    'log_id' => $this->logId,
                    'error'  => $e->getMessage(),
                    'trace'  => $e->getTraceAsString(),
                ]);

                throw $e; // Re-throw agar job di-retry
            }
        });
    }

    private function process(PaymentLog $log): void
    {
        // 1. Ambil payload
        $payload = $log->payload;

        // 2. Jika ternyata masih berupa string (karena lupa cast), decode secara manual
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        // 3. Jika payload kosong atau gagal decode
        if (!is_array($payload)) {
            Log::error('Invalid payload format in PaymentLog', ['log_id' => $log->id]);
            throw new \RuntimeException('Invalid payload format');
        }

        $xenditId  = $this->extractXenditId($payload);

        // Cari payment berdasarkan xendit_id
        $payment = Payment::where('xendit_id', $xenditId)->first();

        if (! $payment) {
            Log::warning('Payment not found for webhook', ['xendit_id' => $xenditId]);
            return; // Bukan payment kita — abaikan
        }

        // Link log ke payment
        $log->update(['payment_id' => $payment->id]);

        // Skip jika sudah final (idempotency layer ke-2)
        if ($payment->status->isFinal()) {
            Log::info('Payment already final, skipping', ['payment_id' => $payment->id]);
            return;
        }

        $xenditStatus = $this->extractStatus($payload);
        $newStatus    = $this->mapXenditStatus($xenditStatus);

        $this->updatePayment($payment, $newStatus, $payload);
        $this->updateOrder($payment, $newStatus);
        $this->dispatchEvents($payment, $newStatus);
    }

    private function updatePayment(Payment $payment, PaymentStatus $newStatus, array $payload): void
    {
        $payment->update([
            'status'           => $newStatus,
            'paid_at'          => $newStatus->isSuccessful() ? now() : null,
            'xendit_payment_id'=> $payload['id'] ?? $payment->xendit_payment_id,
            'xendit_metadata'  => array_merge($payment->xendit_metadata ?? [], $payload),
        ]);
    }

    private function updateOrder(Payment $payment, PaymentStatus $newStatus): void
    {
        $order = $payment->order;

        if ($newStatus->isSuccessful()) {
            $order->transitionTo(
                next  : OrderStatus::Confirmed,
                notes : 'Pembayaran diterima dari Xendit.',
            );
        } elseif ($newStatus === PaymentStatus::Failed || $newStatus === PaymentStatus::Expired) {
            // Kembalikan stok jika payment gagal/expired
            foreach ($order->items as $item) {
                \App\Modules\Product\Models\ProductVariant::where('id', $item->product_variant_id)
                    ->increment('stock', $item->quantity);
            }

            // Kembalikan poin jika pernah di-redeem
            if ($order->points_used > 0) {
                \App\Jobs\RefundRewardPoints::dispatch($order->user_id, $order->points_used, $order->id)
                    ->onQueue('high');
            }

            $order->transitionTo(
                next  : OrderStatus::Cancelled,
                notes : "Pembayaran {$newStatus->value}.",
            );
        }
    }

    private function dispatchEvents(Payment $payment, PaymentStatus $newStatus): void
    {
        if ($newStatus->isSuccessful()) {
            event(new PaymentSucceeded($payment));
        } elseif (in_array($newStatus, [PaymentStatus::Failed, PaymentStatus::Expired])) {
            event(new PaymentFailed($payment));
        }
    }

    private function mapXenditStatus(string $xenditStatus): PaymentStatus
    {
        return match (strtoupper($xenditStatus)) {
            'PAID', 'COMPLETED', 'SUCCEEDED', 'SETTLED' => PaymentStatus::Paid,
            'FAILED', 'CANCELED'                         => PaymentStatus::Failed,
            'EXPIRED'                                    => PaymentStatus::Expired,
            'VOIDED'                                     => PaymentStatus::Voided,
            default                                      => PaymentStatus::Waiting,
        };
    }

    private function extractXenditId(array $payload): string
    {
        // Prioritaskan ID spesifik sesuai dengan jenis metode pembayaran
        return $payload['callback_virtual_account_id'] // Untuk Virtual Account
            ?? $payload['qr_id']                       // Untuk QRIS
            ?? $payload['id']                          // Default (Retail Outlet / Event ID)
            ?? $payload['reference_id']                // eWallet
            ?? throw new \RuntimeException('Cannot extract Xendit ID from payload');
    }

    private function extractStatus(array $payload): string
    {
        // 1. Khusus Virtual Account:
        // Jika webhook memiliki 'payment_id' dan 'callback_virtual_account_id',
        // artinya pelanggan baru saja transfer (pembayaran sukses).
        if (isset($payload['payment_id']) && isset($payload['callback_virtual_account_id'])) {
            return 'PAID';
        }

        // 2. Untuk eWallet, QRIS, Retail Outlet yang memiliki key status eksplisit
        return $payload['status']
            ?? $payload['payment_status']
            ?? 'UNKNOWN';
    }

    public function failed(\Throwable $e): void
    {
        Log::critical('ProcessXenditWebhook job permanently failed', [
            'log_id' => $this->logId,
            'error'  => $e->getMessage(),
        ]);

        // Notifikasi tim ops via Slack/PagerDuty (implementasi sesuai kebutuhan)
        // SlackAlert::send("Payment webhook failed: {$this->logId}");
    }
}
