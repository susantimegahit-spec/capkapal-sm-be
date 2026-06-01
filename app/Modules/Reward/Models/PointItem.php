<?php

namespace App\Modules\Reward\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'name', 'type', 'stock', 'point_cost', 
    'discount_type', 'discount_value', 'expiry_days', 'is_active', 'eligible_for'
])]
class PointItem extends Model
{
    use HasFactory;

    protected $casts = [
        'is_active' => 'boolean',
        'discount_value' => 'decimal:2',
    ];
}
