<?php

namespace App\Modules\Product\Enums;

enum ProductStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Draft    = 'draft';

    public function label(): string
    {
        return match($this) {
            self::Active   => 'Aktif',
            self::Inactive => 'Tidak Aktif',
            self::Draft    => 'Draft',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::Active;
    }
}
