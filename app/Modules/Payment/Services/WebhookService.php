<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Jobs\ProcessXenditWebhook;
use App\Modules\Payment\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Entry point semua webhook dari Xendit.
     * Harus selesai dalam < 30 detik — langsung log + dispatch job.
     */
    public function handle(Request $request): void
    {
        $payload   = $request->all();
        $eventId   = $this->resolveEventId($payload);
        $eventType = $this->resolveEventType($request, $payload);

        Log::info('Xendit webhook received', [
            'event_id'   => $eventId,
            'event_type' => $eventType,
        ]);

        // Idempotency: firstOrCreate mencegah duplikat processing
        [$log, $created] = $this->logWebhook($eventId, $eventType, $payload);

        if (! $created) {
            Log::info('Webhook duplicate, skipping', ['xendit_event_id' => $eventId]);
            return; // Sudah pernah di-log — abaikan duplikat
        }

        // Dispatch job ke 'critical' queue — akan diproses oleh worker
        ProcessXenditWebhook::dispatch($log->id)->onQueue('critical');
    }

    /**
     * Verifikasi X-Callback-Token dari Xendit.
     * Dipanggil dari middleware sebelum request masuk controller.
     */
    public function verifySignature(Request $request): bool
    {
        $token          = $request->header('X-Callback-Token');
        $expectedToken  = config('services.xendit.webhook_token');

        if (empty($token) || empty($expectedToken)) {
            return false;
        }

        return hash_equals($expectedToken, $token);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function logWebhook(string $eventId, string $eventType, array $payload): array
    {
        $existing = PaymentLog::where('xendit_event_id', $eventId)->first();

        if ($existing) {
            return [$existing, false];
        }

        $log = PaymentLog::create([
            'xendit_event_id' => $eventId,
            'event_type'      => $eventType,
            'payload'         => $payload,
            'status'          => 'received',
            'received_at'     => now(),
        ]);

        return [$log, true];
    }

    private function resolveEventId(array $payload): string
    {
        // Xendit mengirimkan 'id' di semua tipe webhook
        return $payload['id']
            ?? $payload['external_id']
            ?? md5(json_encode($payload) . now()->timestamp);
    }

    private function resolveEventType(Request $request, array $payload): string
    {
        // Beberapa webhook Xendit punya header 'x-callback-token' dan body berbeda
        // Kita deteksi dari struktur payload
        if (isset($payload['payment_id'])) {
            return 'payment.completed';
        }
        if (isset($payload['ewallet_transaction_id'])) {
            return 'ewallet.payment.completed';
        }
        if (isset($payload['account_number'])) {
            return 'virtual_account.payment.completed';
        }
        if (isset($payload['qr_id'])) {
            return 'qr_code.payment.completed';
        }
        if (isset($payload['payment_code'])) {
            return 'retail_outlet.payment.completed';
        }

        return $payload['event'] ?? 'unknown';
    }
}
