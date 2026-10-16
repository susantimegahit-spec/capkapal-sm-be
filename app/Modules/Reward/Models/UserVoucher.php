<?php

namespace App\Modules\Reward\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Modules\Auth\Models\User;

#[Fillable([
    'user_id', 'redemption_id', 'code', 'discount_type', 
    'discount_value', 'status', 'valid_until', 'used_at'
])]
class UserVoucher extends Model
{
    use HasFactory;

    protected $casts = [
        'valid_until' => 'datetime',
        'used_at' => 'datetime',
        'discount_value' => 'decimal:2',
    ];

    protected $appends = ['pointItem'];

    public function getPointItemAttribute()
    {
        return $this->redemption ? $this->redemption->pointItem : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function redemption()
    {
        return $this->belongsTo(Redemption::class);
    }
}
