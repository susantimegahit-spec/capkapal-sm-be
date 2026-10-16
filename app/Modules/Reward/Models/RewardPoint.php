<?php

namespace App\Modules\Reward\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RewardPoint extends Model
{
    use HasUuids;
    protected $table = 'reward_points';

    protected $fillable = [
        'user_id',
        'balance',
        'lifetime_earned',
        'lifetime_redeemed',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'lifetime_earned' => 'integer',
            'lifetime_redeemed' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
