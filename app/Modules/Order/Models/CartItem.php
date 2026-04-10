<?php

namespace App\Modules\Order\Models;

use App\Modules\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo};

class CartItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'cart_id', 'product_variant_id',
        'quantity', 'unit_price', 'notes',
    ];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getSubtotalAttribute(): float
    {
        return (float) ($this->unit_price * $this->quantity);
    }
}
