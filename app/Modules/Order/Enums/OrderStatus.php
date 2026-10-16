<?php

namespace App\Modules\Order\Enums;

enum OrderStatus: string
{
    case Pending    = 'pending';
    case Confirmed  = 'confirmed';
    case Processing = 'processing';
    case Shipped    = 'shipped';
    case Delivered  = 'delivered';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';
    case Refunded   = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Pending    => 'Menunggu Pembayaran',
            self::Confirmed  => 'Pembayaran Diterima',
            self::Processing => 'Sedang Diproses',
            self::Shipped    => 'Dalam Pengiriman',
            self::Delivered  => 'Sudah Diterima',
            self::Completed  => 'Selesai',
            self::Cancelled  => 'Dibatalkan',
            self::Refunded   => 'Dikembalikan',
        };
    }

    /** Status yang boleh diubah dari status ini */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::Pending    => [self::Confirmed, self::Cancelled],
            self::Confirmed  => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped    => [self::Delivered],
            self::Delivered  => [self::Completed, self::Refunded],
            self::Completed  => [],
            self::Cancelled  => [],
            self::Refunded   => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions());
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Refunded]);
    }
}
