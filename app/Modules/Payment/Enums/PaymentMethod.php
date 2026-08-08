<?php

namespace App\Modules\Payment\Enums;

enum PaymentMethod: string
{
    case VirtualAccount = 'virtual_account';
    case Ewallet        = 'ewallet';
    case Qris           = 'qris';
    case CreditCard     = 'credit_card';
    case RetailOutlet   = 'retail_outlet';
    case Flat           = 'flat';
    case ManualQris     = 'manual_qris';
}
