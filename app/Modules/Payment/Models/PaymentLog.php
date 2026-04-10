<?php

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'xendit_event_id', 'event_type',
        'payload', 'status', 'error_message',
        'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'received_at'  => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
