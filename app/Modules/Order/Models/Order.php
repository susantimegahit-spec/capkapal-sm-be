<?php

namespace App\Modules\Order\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Order\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'order_number', 'status',
        'subtotal', 'discount_amount', 'coupon_discount',
        'points_discount', 'shipping_fee', 'total',
        'coupon_code', 'points_used', 'points_earned',
        'shipping_courier', 'shipping_service',
        'tracking_number', 'estimated_delivery',
        'customer_notes', 'admin_notes',
        'confirmed_at', 'shipped_at', 'delivered_at',
        'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status'             => OrderStatus::class,
            'subtotal'           => 'decimal:2',
            'discount_amount'    => 'decimal:2',
            'coupon_discount'    => 'decimal:2',
            'points_discount'    => 'decimal:2',
            'shipping_fee'       => 'decimal:2',
            'total'              => 'decimal:2',
            'estimated_delivery' => 'date',
            'confirmed_at'       => 'datetime',
            'shipped_at'         => 'datetime',
            'delivered_at'       => 'datetime',
            'completed_at'       => 'datetime',
            'cancelled_at'       => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(\App\Modules\Payment\Models\Payment::class);
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(ShippingAddress::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function canBeCancelledByUser(): bool
    {
        return in_array($this->status, [OrderStatus::Pending, OrderStatus::Confirmed]);
    }

    public function transitionTo(OrderStatus $next, ?string $notes = null, ?string $actorId = null): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new \DomainException(
                "Tidak bisa mengubah status dari {$this->status->label()} ke {$next->label()}."
            );
        }

        $timestampField = match($next) {
            OrderStatus::Confirmed  => 'confirmed_at',
            OrderStatus::Shipped    => 'shipped_at',
            OrderStatus::Delivered  => 'delivered_at',
            OrderStatus::Completed  => 'completed_at',
            OrderStatus::Cancelled  => 'cancelled_at',
            default                 => null,
        };

        $this->update(array_filter([
            'status'        => $next,
            $timestampField => $timestampField ? now() : null,
        ]));

        $this->statusHistories()->create([
            'status'     => $next->value,
            'notes'      => $notes,
            'created_by' => $actorId,
        ]);
    }
}
