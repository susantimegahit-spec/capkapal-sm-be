<?php

namespace App\Modules\Order\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingAddress extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id', 'recipient_name', 'phone',
        'address', 'district', 'city',
        'province', 'postal_code', 'country',
        'latitude', 'longitude',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
