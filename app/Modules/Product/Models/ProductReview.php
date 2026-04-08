<?php

namespace App\Modules\Product\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductReview extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'product_id', 'user_id', 'order_item_id',
        'rating', 'content', 'images',
        'is_verified_purchase', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'images'               => 'array',
            'is_verified_purchase' => 'boolean',
            'is_published'         => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
