<?php

namespace App\Modules\Payment\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\{PaymentMethod, PaymentStatus};
use App\Modules\Payment\Gateway\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Models\Payment;
use Illuminate\Support\Facades\{DB, Log};

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Dipanggil dari CheckoutService di dalam DB::transaction().
     * Membuat payment record + call Xendit API.
     */
    public function createPaymentForOrder(
        Order   $order,
        string  $method,
        ?string $provider = null
    ): array {
        $paymentMethod = PaymentMethod::from($method);

        // Panggil Xendit sesuai metode
        $gatewayResult = match ($paymentMethod) {
            PaymentMethod::VirtualAccount => $this->gateway->createVirtualAccount($order, $provider),
            PaymentMethod::Ewallet        => $this->gateway->createEwallet($order, $provider,
                config('app.frontend_url') . '/order/' . $order->order_number),
            PaymentMethod::Qris           => $this->gateway->createQris($order),
            PaymentMethod::RetailOutlet   => $this->gateway->createRetailOutlet($order, $provider),
            PaymentMethod::Flat           => $this->gateway->createFlatTesting($order, $provider),
            PaymentMethod::CreditCard     => throw new \RuntimeException('Credit card belum diimplementasi.'),
        };

        // Simpan payment record
        $payment = Payment::create([
            'order_id'        => $order->id,
            'xendit_id'       => $gatewayResult['xendit_id'],
            'method'          => $paymentMethod,
            'provider'        => $provider,
            'status'          => PaymentStatus::Waiting,
            'amount'          => $order->total,
            'currency'        => 'IDR',
            'payment_code'    => $gatewayResult['payment_code']  ?? null,
            'payment_url'     => $gatewayResult['payment_url']   ?? null,
            'qr_string'       => $gatewayResult['qr_string']     ?? null,
            'expired_at'      => $gatewayResult['expired_at']
                ? \Carbon\Carbon::parse($gatewayResult['expired_at'])
                : now()->addDay(),
            'xendit_metadata' => $gatewayResult['metadata']      ?? null,
        ]);

        return $payment->instructions;
    }

    /**
     * Cek status pembayaran ke Xendit (untuk polling dari frontend).
     */
    public function checkStatus(string $orderId, string $userId): array
    {
        $payment = Payment::whereHas('order', fn($q) =>
        $q->where('id', $orderId)->where('user_id', $userId)
        )->firstOrFail();

        if ($payment->status->isFinal()) {
            return [
                'status'      => $payment->status->value,
                'paid_at'     => $payment->paid_at?->toISOString(),
                'instructions'=> null,
            ];
        }

        // Check expired
        if ($payment->isExpired()) {
            $payment->update(['status' => PaymentStatus::Expired]);
            return ['status' => 'expired', 'instructions' => null];
        }

        return [
            'status'       => $payment->status->value,
            'instructions' => $payment->instructions,
            'expired_at'   => $payment->expired_at?->toISOString(),
        ];
    }

    /**
     * Buat ulang payment untuk order yang expired/failed.
     */
    public function retryPayment(string $orderNumber, string $userId, string $method, ?string $provider): array
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', $userId)
            ->with('payment')
            ->firstOrFail();

        if (! in_array($order->status->value, ['pending'])) {
            throw new \DomainException('Order tidak bisa diretry.');
        }

        // Hapus payment lama
        $order->payment?->delete();

        return $this->createPaymentForOrder($order, $method, $provider);
    }
}
