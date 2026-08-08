<?php

namespace App\Modules\Payment\Enums;

enum PaymentStatus: string
{
    case Pending              = 'pending';
    case Waiting              = 'waiting';
    case Paid                 = 'paid';
    case Failed               = 'failed';
    case Expired              = 'expired';
    case Refunded             = 'refunded';
    case Voided               = 'voided';
    case AwaitingVerification = 'awaiting_verification';
    case Rejected             = 'rejected';

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Failed, self::Expired, self::Refunded, self::Voided]);
    }

    public function isSuccessful(): bool
    {
        return $this === self::Paid;
    }
}
