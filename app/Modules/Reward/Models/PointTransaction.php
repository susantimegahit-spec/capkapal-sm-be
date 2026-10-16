<?php

namespace App\Modules\Reward\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Modules\Auth\Models\User;

#[Fillable([
    'user_id', 'amount', 'type', 'description', 
    'reference_type', 'reference_id'
])]
class PointTransaction extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
