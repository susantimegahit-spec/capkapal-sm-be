<?php

namespace App\Modules\Order\Models;

use App\Modules\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo};

class OrderItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id', 'product_variant_id',
        'product_name', 'variant_name', 'sku',
        'unit_price', 'quantity', 'subtotal',
        'weight', 'product_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'       => 'decimal:2',
            'subtotal'         => 'decimal:2',
            'weight'           => 'decimal:2',
            'product_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Buat snapshot produk saat order untuk keperluan audit.
     * Data produk tidak berubah meskipun produk diubah/dihapus setelahnya.
     */
    public static function buildSnapshot(ProductVariant $variant): array
    {
        return [
            'product_id'    => $variant->product_id,
            'product_name'  => $variant->product->name,
            'variant_name'  => $variant->name,
            'sku'           => $variant->sku,
            'price'         => $variant->price,
            'weight'        => $variant->weight,
            'attributes'    => $variant->attributes,
            'image_url'     => $variant->product->primary_image_url,
        ];
    }
}
