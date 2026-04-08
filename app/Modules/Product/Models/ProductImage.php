<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id', 'url', 'alt', 'sort_order', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Jika gambar baru di-set primary, unset gambar lain
        static::saving(function (self $image) {
            if ($image->is_primary) {
                static::where('product_id', $image->product_id)
                    ->where('id', '!=', $image->id)
                    ->update(['is_primary' => false]);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->attributes['url']
            ? \Storage::url($this->attributes['url'])
            : null;
    }
}
