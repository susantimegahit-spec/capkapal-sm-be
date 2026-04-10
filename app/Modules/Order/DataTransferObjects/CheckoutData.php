<?php

namespace App\Modules\Order\DataTransferObjects;

use Spatie\LaravelData\Data;

class CheckoutData extends Data
{
    public function __construct(
        // Shipping
        public readonly string  $recipient_name,
        public readonly string  $phone,
        public readonly string  $address,
        public readonly string  $city,
        public readonly string  $province,
        public readonly string  $postal_code,
        public readonly string  $shipping_courier,  // jne, jnt, sicepat, flat
        public readonly string  $shipping_service,  // reg, yes, oke

        // Payment
        public readonly string  $payment_method,    // virtual_account, ewallet, qris, credit_card
        public readonly ?string $payment_provider,  // bca, ovo, dana, gopay, dll.

        // Optional
        public readonly ?string $district       = null,
        public readonly ?string $coupon_code    = null,
        public readonly int     $points_used    = 0,
        public readonly ?string $customer_notes = null,
    ) {}
}
