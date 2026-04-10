<?php

namespace App\Modules\Payment\Gateway;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Gateway\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditGateway implements PaymentGatewayInterface
{
    private string $baseUrl   = 'https://api.xendit.co';
    private string $secretKey;
    private string $callbackUrl;

    public function __construct()
    {
        $this->secretKey   = config('services.xendit.secret_key');
        $this->callbackUrl = config('services.xendit.callback_url');
    }

    // ── Virtual Account ───────────────────────────────────────────────────────

    public function createVirtualAccount(Order $order, string $bank): array
    {
        $externalId = $this->buildExternalId($order, 'va');

        $response = $this->post('/callback_virtual_accounts', [
            'external_id'      => $externalId,
            'bank_code'        => strtoupper($bank),
            'name'             => $order->user->name,
            'expected_amount'  => (int) $order->total,
            'expiration_date'  => now()->addDay()->toISOString(),
            'is_single_use'    => true,
            'is_closed'        => true,
        ]);

        return [
            'xendit_id'    => $response['id'],
            'payment_code' => $response['account_number'],
            'expired_at'   => $response['expiration_date'],
            'metadata'     => $response,
        ];
    }

    // ── E-Wallet ──────────────────────────────────────────────────────────────

    public function createEwallet(Order $order, string $provider, string $successReturnUrl = ''): array
    {
        $externalId = $this->buildExternalId($order, 'ew');

        $response = $this->post('/ewallets/charges', [
            'reference_id'      => $externalId,
            'currency'          => 'IDR',
            'amount'            => (int) $order->total,
            'checkout_method'   => 'ONE_TIME_PAYMENT',
            'channel_code'      => strtoupper($provider),
            'channel_properties' => [
                'success_redirect_url' => $successReturnUrl ?: config('app.frontend_url') . '/order/' . $order->order_number,
                'failure_redirect_url' => config('app.frontend_url') . '/order/' . $order->order_number . '?payment=failed',
            ],
            'metadata' => ['order_number' => $order->order_number],
        ]);

        return [
            'xendit_id'   => $response['id'],
            'payment_url' => $response['actions']['desktop_web_checkout_url']
                ?? $response['actions']['mobile_web_checkout_url']
                    ?? null,
            'expired_at'  => now()->addHour()->toISOString(),
            'metadata'    => $response,
        ];
    }

    // ── QRIS ──────────────────────────────────────────────────────────────────

    public function createQris(Order $order): array
    {
        $referenceId = $this->buildExternalId($order, 'qr');

        $response = $this->post('/qr_codes', [
            'reference_id'  => $referenceId,
            'type'         => 'DYNAMIC',
            'currency'     => 'IDR',
            'amount'       => (int) $order->total,
            'expires_at'   => now()->addHours(2)->toISOString(),
//            'callback_url' => $this->callbackUrl,
        ]);

        return [
            'xendit_id'  => $response['id'],
            'qr_string'  => $response['qr_string'],
            'payment_url'=> $response['qr_string'], // QR image dirender di frontend
            'expired_at' => $response['expires_at'],
            'metadata'   => $response,
        ];
    }

    // ── Retail Outlet ─────────────────────────────────────────────────────────

    public function createRetailOutlet(Order $order, string $retailer): array
    {
        $externalId = $this->buildExternalId($order, 'ro');

        $response = $this->post('/payment_codes', [
            'external_id'       => $externalId,
            'retail_outlet_name'=> strtoupper($retailer),
            'name'              => $order->user->name,
            'expected_amount'   => (int) $order->total,
            'expiration_date'   => now()->addDay()->toISOString(),
        ]);

        return [
            'xendit_id'    => $response['id'],
            'payment_code' => $response['payment_code'],
            'expired_at'   => $response['expiration_date'],
            'metadata'     => $response,
        ];
    }

    public function createFlatTesting(Order $order, string $provider): array
    {
        $externalId = $this->buildExternalId($order, 'ft');

        $response = $this->post('/payment_requests', [
            'external_id' => $externalId,
            'amount'      => (int) $order->total,
            'payment_method' => strtoupper($provider),
            'description' => "Payment for order {$order->order_number}",
        ]);

        return [
            'xendit_id' => $response['id'],
            'metadata'   => $response,
        ];
    }

    // ── Status Check ──────────────────────────────────────────────────────────

    public function getPaymentStatus(string $xenditId, string $method): array
    {
        $endpoint = match ($method) {
            'virtual_account' => "/callback_virtual_account_payments/payment_id={$xenditId}",
            'ewallet'         => "/ewallets/charges/{$xenditId}",
            'qris'            => "/qr_codes/{$xenditId}",
            'retail_outlet'   => "/payment_codes/{$xenditId}",
            default           => "/payment_requests/{$xenditId}",
        };

        return $this->get($endpoint);
    }

    // ── Void / Refund ─────────────────────────────────────────────────────────

    public function voidPayment(string $xenditId): bool
    {
        try {
            $this->post("/ewallets/charges/{$xenditId}/void");
            return true;
        } catch (\Exception $e) {
            Log::warning('Xendit void failed', ['xendit_id' => $xenditId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function createRefund(string $xenditPaymentId, float $amount, string $reason): array
    {
        return $this->post('/refunds', [
            'payment_id' => $xenditPaymentId,
            'amount'     => (int) $amount,
            'reason'     => $reason,
        ]);
    }

    // ── HTTP Helpers ──────────────────────────────────────────────────────────

    private function post(string $endpoint, array $payload = []): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->withHeaders(['api-version' => '2022-07-31'])
            ->timeout(30)
            ->retry(2, 500)
            ->post($this->baseUrl . $endpoint, $payload);

        if ($response->failed()) {
            Log::error('Xendit API error', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => $response->json(),
            ]);

            throw new \RuntimeException(
                'Xendit API error: ' . ($response->json('message') ?? $response->body()),
                $response->status()
            );
        }

        return $response->json();
    }

    private function get(string $endpoint): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->withHeaders(['api-version' => '2022-07-31'])
            ->timeout(30)
            ->get($this->baseUrl . $endpoint);

        if ($response->failed()) {
            throw new \RuntimeException('Xendit API error: ' . $response->body(), $response->status());
        }

        return $response->json();
    }

    private function buildExternalId(Order $order, string $prefix): string
    {
        return strtoupper($prefix) . '-' . $order->order_number . '-' . now()->timestamp;
    }
}
