<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
  use HasUuids;

  protected $fillable = [
    'user_id',
    'label',
    'recipient_name',
    'phone',
    'province',
    'city',
    'district',
    'postal_code',
    'address',
    'is_primary',
    'village'
  ];

  protected function casts(): array
  {
    return [
      'is_primary' => 'boolean',
    ];
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }
}