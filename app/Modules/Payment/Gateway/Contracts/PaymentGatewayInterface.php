<?php

namespace App\Modules\Payment\Gateway\Contracts;

use App\Modules\Order\Models\Order;

interface PaymentGatewayInterface
{
    public function createVirtualAccount(Order $order, string $bank): array;

    public function createEwallet(Order $order, string $provider, string $successReturnUrl): array;

    public function createQris(Order $order): array;

    public function createRetailOutlet(Order $order, string $retailer): array;
    public function createFlatTesting(Order $order, string $provider);

    public function getPaymentStatus(string $xenditId, string $method): array;

    public function voidPayment(string $xenditId): bool;

    public function createRefund(string $xenditPaymentId, float $amount, string $reason): array;
}
