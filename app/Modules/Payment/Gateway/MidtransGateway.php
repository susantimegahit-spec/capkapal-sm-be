<?php

namespace App\Modules\Payment\Gateway;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Gateway\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use Midtrans\Config as MidtransConfig;
use Midtrans\CoreApi;
use Midtrans\Transaction;

class MidtransGateway implements PaymentGatewayInterface
{
    public function __construct()
    {
        MidtransConfig::$serverKey    = config('services.midtrans.server_key');
        MidtransConfig::$isProduction = (bool) config('services.midtrans.is_production', false);
        MidtransConfig::$isSanitized  = true;
        MidtransConfig::$is3ds        = true;
    }

    // ── Virtual Account ───────────────────────────────────────────────────────

    public function createVirtualAccount(Order $order, string $bank): array
    {
        $orderId = $this->buildOrderId($order, 'va');

        $params = [
            'payment_type'        => 'bank_transfer',
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $order->total,
            ],
            'customer_details' => $this->buildCustomerDetails($order),
            'bank_transfer'    => [
                'bank' => strtolower($bank),
            ],
        ];

        // Permata uses different structure
        if (strtolower($bank) === 'permata') {
            unset($params['bank_transfer']);
            $params['payment_type'] = 'permata';
        }

        $response = $this->charge($params);

        // Extract VA number from response
        $paymentCode = $this->extractVaNumber($response, $bank);

        return [
            'midtrans_order_id' => $orderId,
            'payment_code'      => $paymentCode,
            'expired_at'        => $response->expiry_time ?? now()->addDay()->toISOString(),
            'metadata'          => (array) $response,
        ];
    }

    // ── E-Wallet ──────────────────────────────────────────────────────────────

    public function createEwallet(Order $order, string $provider, string $successReturnUrl = ''): array
    {
        $orderId     = $this->buildOrderId($order, 'ew');
        $paymentType = strtolower($provider); // 'gopay', 'shopeepay'

        $params = [
            'payment_type'        => $paymentType,
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $order->total,
            ],
            'customer_details' => $this->buildCustomerDetails($order),
        ];

        // Add provider-specific config
        if ($paymentType === 'gopay') {
            $params['gopay'] = [
                'enable_callback' => true,
                'callback_url'    => $successReturnUrl ?: config('app.frontend_url') . '/order/' . $order->order_number,
            ];
        } elseif ($paymentType === 'shopeepay') {
            $params['shopeepay'] = [
                'callback_url' => $successReturnUrl ?: config('app.frontend_url') . '/order/' . $order->order_number,
            ];
        }

        $response = $this->charge($params);

        // Extract payment URL from actions
        $paymentUrl = $this->extractActionUrl($response);

        return [
            'midtrans_order_id' => $orderId,
            'payment_url'       => $paymentUrl,
            'expired_at'        => $response->expiry_time ?? now()->addHour()->toISOString(),
            'metadata'          => (array) $response,
        ];
    }

    // ── QRIS ──────────────────────────────────────────────────────────────────

    public function createQris(Order $order): array
    {
        $orderId = $this->buildOrderId($order, 'qr');

        $params = [
            'payment_type'        => 'qris',
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $order->total,
            ],
            'customer_details' => $this->buildCustomerDetails($order),
            'qris' => [
                'acquirer' => 'gopay',
            ],
        ];

        $response = $this->charge($params);

        // Extract QR string/URL from actions
        $qrUrl = $this->extractActionUrl($response, 'generate-qr-code');

        return [
            'midtrans_order_id' => $orderId,
            'qr_string'         => $qrUrl,
            'payment_url'        => $qrUrl,
            'expired_at'         => $response->expiry_time ?? now()->addHours(2)->toISOString(),
            'metadata'           => (array) $response,
        ];
    }

    // ── Retail Outlet (CStore) ────────────────────────────────────────────────

    public function createRetailOutlet(Order $order, string $retailer): array
    {
        $orderId = $this->buildOrderId($order, 'ro');

        $params = [
            'payment_type'        => 'cstore',
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $order->total,
            ],
            'customer_details' => $this->buildCustomerDetails($order),
            'cstore' => [
                'store'   => strtolower($retailer),
                'message' => "Pembayaran pesanan {$order->order_number}",
            ],
        ];

        $response = $this->charge($params);

        return [
            'midtrans_order_id' => $orderId,
            'payment_code'      => $response->payment_code ?? null,
            'expired_at'        => $response->expiry_time ?? now()->addDay()->toISOString(),
            'metadata'          => (array) $response,
        ];
    }

    // ── Flat Testing ──────────────────────────────────────────────────────────

    public function createFlatTesting(Order $order, string $provider): array
    {
        // For testing purposes, create a simple bank_transfer charge
        $orderId = $this->buildOrderId($order, 'ft');

        $params = [
            'payment_type'        => 'bank_transfer',
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $order->total,
            ],
            'customer_details' => $this->buildCustomerDetails($order),
            'bank_transfer'    => [
                'bank' => 'bca',
            ],
        ];

        $response = $this->charge($params);

        return [
            'midtrans_order_id' => $orderId,
            'metadata'          => (array) $response,
        ];
    }

    // ── Status Check ──────────────────────────────────────────────────────────

    public function getPaymentStatus(string $gatewayId, string $method): array
    {
        try {
            $response = Transaction::status($gatewayId);
            return (array) $response;
        } catch (\Exception $e) {
            Log::error('Midtrans status check failed', [
                'order_id' => $gatewayId,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ── Void / Cancel ─────────────────────────────────────────────────────────

    public function voidPayment(string $gatewayId): bool
    {
        try {
            Transaction::cancel($gatewayId);
            return true;
        } catch (\Exception $e) {
            Log::warning('Midtrans cancel failed', [
                'order_id' => $gatewayId,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ── Refund ────────────────────────────────────────────────────────────────

    public function createRefund(string $transactionId, float $amount, string $reason): array
    {
        try {
            $params = [
                'refund_key' => 'refund-' . $transactionId . '-' . now()->timestamp,
                'amount'     => (int) $amount,
                'reason'     => $reason,
            ];

            $response = Transaction::refund($transactionId, $params);
            return (array) $response;
        } catch (\Exception $e) {
            Log::error('Midtrans refund failed', [
                'transaction_id' => $transactionId,
                'error'          => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function charge(array $params): object
    {
        try {
            $response = CoreApi::charge($params);

            Log::info('Midtrans charge success', [
                'order_id' => $params['transaction_details']['order_id'],
                'status'   => $response->transaction_status ?? 'unknown',
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Midtrans API error', [
                'order_id' => $params['transaction_details']['order_id'] ?? 'unknown',
                'error'    => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                'Midtrans API error: ' . $e->getMessage(),
                (int) $e->getCode()
            );
        }
    }

    private function buildOrderId(Order $order, string $prefix): string
    {
        return strtoupper($prefix) . '-' . $order->order_number . '-' . now()->timestamp;
    }

    private function buildCustomerDetails(Order $order): array
    {
        return [
            'first_name' => $order->user->name ?? 'Customer',
            'email'      => $order->user->email ?? '',
            'phone'      => $order->user->phone ?? '',
        ];
    }

    private function extractVaNumber(object $response, string $bank): ?string
    {
        // BCA, BNI, BRI return va_numbers array
        if (isset($response->va_numbers) && count($response->va_numbers) > 0) {
            return $response->va_numbers[0]->va_number;
        }

        // Permata returns permata_va_number
        if (isset($response->permata_va_number)) {
            return $response->permata_va_number;
        }

        return null;
    }

    private function extractActionUrl(object $response, ?string $name = null): ?string
    {
        if (! isset($response->actions) || ! is_array($response->actions)) {
            return null;
        }

        foreach ($response->actions as $action) {
            if ($name && $action->name === $name) {
                return $action->url;
            }
        }

        // Return first action URL if no specific name requested
        return $response->actions[0]->url ?? null;
    }
}
