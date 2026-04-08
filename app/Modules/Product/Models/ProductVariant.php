<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id', 'name', 'sku', 'price',
        'price_distributor', 'price_umkm', 'stock',
        'low_stock_threshold', 'weight', 'attributes',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price'              => 'decimal:2',
            'price_distributor'  => 'decimal:2',
            'price_umkm'         => 'decimal:2',
            'weight'             => 'decimal:2',
            'attributes'         => 'array',
            'is_active'          => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= $this->low_stock_threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock <= 0;
    }

    /**
     * Harga sesuai user_type, default ke price biasa.
     */
    public function getPriceForUserType(string $userType): float
    {
        return (float) match ($userType) {
            'distributor' => $this->price_distributor ?? $this->price,
            'umkm'        => $this->price_umkm        ?? $this->price,
            default       => $this->price,
        };
    }
}
