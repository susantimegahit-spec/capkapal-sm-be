<?php

namespace App\Modules\Payment\Models;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\{PaymentMethod, PaymentStatus};
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id', 'midtrans_order_id', 'midtrans_transaction_id',
        'method', 'provider', 'status',
        'amount', 'currency',
        'payment_code', 'payment_url', 'qr_string',
        'paid_at', 'expired_at', 'gateway_metadata',
    ];

    protected function casts(): array
    {
        return [
            'method'           => PaymentMethod::class,
            'status'           => PaymentStatus::class,
            'amount'           => 'decimal:2',
            'paid_at'          => 'datetime',
            'expired_at'       => 'datetime',
            'gateway_metadata' => 'array',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentLog::class)->latest('received_at');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expired_at && $this->expired_at->isPast()
            && $this->status === PaymentStatus::Waiting;
    }

    public function getInstructionsAttribute(): array
    {
        return match ($this->method) {
            PaymentMethod::VirtualAccount => [
                'type'           => 'virtual_account',
                'bank'           => strtoupper($this->provider),
                'account_number' => $this->payment_code,
                'expired_at'     => $this->expired_at?->toISOString(),
            ],
            PaymentMethod::Ewallet => [
                'type'       => 'ewallet',
                'provider'   => $this->provider,
                'pay_url'    => $this->payment_url,
                'expired_at' => $this->expired_at?->toISOString(),
            ],
            PaymentMethod::Qris => [
                'type'       => 'qris',
                'qr_string'  => $this->qr_string,
                'qr_url'     => $this->payment_url,
                'expired_at' => $this->expired_at?->toISOString(),
            ],
            PaymentMethod::RetailOutlet => [
                'type'         => 'retail_outlet',
                'retailer'     => strtoupper($this->provider),
                'payment_code' => $this->payment_code,
                'expired_at'   => $this->expired_at?->toISOString(),
            ],
            default => [],
        };
    }
}
