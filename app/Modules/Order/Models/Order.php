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

        // 1. Simpan status lama sebelum diupdate
        $oldStatus = $this->status;

        $timestampField = match ($next) {
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

        // 2. Sinkronisasi Total Terjual Produk
        $this->syncProductTotalSold($oldStatus, $next);
    }

    /**
     * Logic untuk menambah/mengurangi total_sold pada Produk
     */
    private function syncProductTotalSold(OrderStatus $oldStatus, OrderStatus $next): void
    {
        // Kondisi 1: Pesanan baru saja sukses dibayar
        $isJustPaid = ($oldStatus === OrderStatus::Pending && $next === OrderStatus::Confirmed);

        // Kondisi 2: Pesanan dibatalkan/direfund SETELAH sebelumnya sudah berstatus dibayar/diproses
        $wasPaid = in_array($oldStatus, [
            OrderStatus::Confirmed,
            OrderStatus::Processing,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
            OrderStatus::Completed
        ]);
        $isCancelledOrRefunded = in_array($next, [OrderStatus::Cancelled, OrderStatus::Refunded]);

        // Jika tidak masuk kedua kondisi di atas, hentikan fungsi
        if (! $isJustPaid && ! ($wasPaid && $isCancelledOrRefunded)) {
            return;
        }

        // Pastikan relasi diload untuk menghindari N+1 query
        $this->loadMissing('items.variant.product');

        foreach ($this->items as $item) {
            $product = $item->variant?->product;

            if ($product) {
                if ($isJustPaid) {
                    // Gunakan atomic increment untuk mencegah race condition
                    $product->increment('total_sold', $item->quantity);
                } elseif ($wasPaid && $isCancelledOrRefunded) {
                    $currentSold = $product->total_sold ?? 0;
                    if ($currentSold > 0) {
                        $decrementAmount = min($currentSold, $item->quantity);
                        $product->decrement('total_sold', $decrementAmount);
                    }
                }

                // Karena increment() tidak memicu event Eloquent (seperti 'saved'),
                // kita perlu memanggil sinkronisasi Scout secara manual.
                if (method_exists($product, 'shouldBeSearchable')) {
                    if ($product->shouldBeSearchable()) {
                        $product->searchable();
                    } else {
                        $product->unsearchable();
                    }
                } elseif (method_exists($product, 'searchable')) {
                    $product->searchable();
                }
            }
        }

        // SANGAT PENTING: Bersihkan cache produk agar UI Frontend langsung ter-refresh!
        if (class_exists(\Illuminate\Support\Facades\Cache::class)) {
            \Illuminate\Support\Facades\Cache::tags(['products'])->flush();
        }
    }
}
