<?php

namespace App\Modules\Order\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * Tabel yang diasosiasikan dengan model.
     * (Opsional jika nama tabel sudah jamak dari nama model, tapi aman untuk didefinisikan)
     */
    protected $table = 'coupons';

    /**
     * Atribut yang dapat diisi secara massal (Mass Assignment).
     */
    protected $fillable = [
        'promo_id',
        'code',
        'discount_type',
        'discount_value',
        'max_discount',
        'min_purchase',
        'max_uses',
        'max_uses_per_user',
        'used_count',
        'user_type_restriction',
        'specific_user_id',
        'expires_at',
        'is_active',
    ];

    /**
     * Tipe casting untuk atribut.
     */
    protected $casts = [
        'discount_value'    => 'decimal:2',
        'max_discount'      => 'decimal:2',
        'min_purchase'      => 'decimal:2',
        'max_uses'          => 'integer',
        'max_uses_per_user' => 'integer',
        'used_count'        => 'integer',
        'is_active'         => 'boolean',
        // Casting ke datetime ini SANGAT PENTING agar fungsi ->isPast()
        // di CouponService Anda bisa berjalan tanpa error.
        'expires_at'        => 'datetime',
    ];

    // ── Relasi ───────────────────────────────────────────────────────────────

    /**
     * Relasi ke user spesifik (jika kupon ini personal).
     */
    public function specificUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'specific_user_id');
    }

    // Jika Anda memiliki model Promo, Anda bisa membuka komentar di bawah ini:
    /*
    public function promo(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Promo\Models\Promo::class, 'promo_id');
    }
    */
}
