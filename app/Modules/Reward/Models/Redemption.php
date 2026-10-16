<?php

namespace App\Modules\Reward\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Modules\Auth\Models\User;

#[Fillable([
    'user_id', 'point_item_id', 'points_used', 'type', 
    'status', 'shipping_address', 'tracking_number'
])]
class Redemption extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pointItem()
    {
        return $this->belongsTo(PointItem::class);
    }

    public function pointTransactions()
    {
        return $this->morphMany(PointTransaction::class, 'reference');
    }

    public function voucher()
    {
        return $this->hasOne(UserVoucher::class);
    }
}
