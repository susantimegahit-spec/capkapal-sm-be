<?php

namespace App\Modules\Order\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Cart extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'session_id', 'coupon_code',
        'coupon_discount', 'points_used', 'points_discount', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'coupon_discount' => 'decimal:2',
            'points_discount' => 'decimal:2',
            'expires_at'      => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getSubtotalAttribute(): float
    {
        return (float) $this->items->sum(fn($item) => $item->unit_price * $item->quantity);
    }

    public function getTotalAttribute(): float
    {
        return max(0, $this->subtotal - $this->coupon_discount - $this->points_discount);
    }

    public function getItemCountAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }
}
