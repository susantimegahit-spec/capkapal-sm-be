<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Jobs\ProcessMidtransNotification;
use App\Modules\Payment\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Entry point semua notification dari Midtrans.
     * Harus selesai dalam < 30 detik — langsung log + dispatch job.
     */
    public function handle(Request $request): void
    {
        $payload   = $request->all();
        $eventId   = $this->resolveEventId($payload);
        $eventType = $this->resolveEventType($payload);

        Log::info('Midtrans notification received', [
            'notification_id' => $eventId,
            'event_type'      => $eventType,
            'order_id'        => $payload['order_id'] ?? 'unknown',
        ]);

        // Idempotency: firstOrCreate mencegah duplikat processing
        [$log, $created] = $this->logWebhook($eventId, $eventType, $payload);

        if (! $created) {
            Log::info('Notification duplicate, skipping', ['notification_id' => $eventId]);
            return; // Sudah pernah di-log — abaikan duplikat
        }

        // Dispatch job ke 'critical' queue — akan diproses oleh worker
        ProcessMidtransNotification::dispatch($log->id)->onQueue('critical');
    }

    /**
     * Verifikasi signature dari Midtrans.
     * Signature: SHA-512(order_id + status_code + gross_amount + ServerKey)
     * Dipanggil dari middleware sebelum request masuk controller.
     */
    public function verifySignature(Request $request): bool
    {
        $payload   = $request->all();
        $serverKey = config('services.midtrans.server_key');

        if (empty($serverKey)) {
            return false;
        }

        $orderId    = $payload['order_id']    ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount= $payload['gross_amount']?? '';
        $signatureKey = $payload['signature_key'] ?? '';

        if (empty($signatureKey) || empty($orderId)) {
            return false;
        }

        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        return hash_equals($expectedSignature, $signatureKey);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function logWebhook(string $eventId, string $eventType, array $payload): array
    {
        try {
            $log = PaymentLog::firstOrCreate(
                ['notification_id' => $eventId],
                [
                    'event_type'  => $eventType,
                    'payload'     => $payload,
                    'status'      => 'received',
                    'received_at' => now(),
                ]
            );

            // Jika wasRecentlyCreated bernilai true, berarti data baru berhasil disisipkan.
            return [$log, $log->wasRecentlyCreated];
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Fallback: Jika terjadi race condition (sangat jarang) yang lolos dari firstOrCreate,
            // kita tangkap exception-nya lalu fetch data yang berhasil masuk.
            $existing = PaymentLog::where('notification_id', $eventId)->first();
            return [$existing, false];
        }
    }

    private function resolveEventId(array $payload): string
    {
        // Midtrans mengirimkan 'transaction_id' dan 'order_id' di semua notification
        // Gunakan kombinasi keduanya untuk idempotency yang kuat
        $transactionId = $payload['transaction_id'] ?? '';
        $status        = $payload['transaction_status'] ?? '';

        if ($transactionId && $status) {
            return $transactionId . ':' . $status;
        }

        // Fallback HARUS deterministik — tanpa komponen waktu.
        return hash('sha256', json_encode($payload));
    }

    private function resolveEventType(array $payload): string
    {
        $transactionStatus = $payload['transaction_status'] ?? 'unknown';
        $paymentType       = $payload['payment_type'] ?? 'unknown';

        return "{$paymentType}.{$transactionStatus}";
    }
}
