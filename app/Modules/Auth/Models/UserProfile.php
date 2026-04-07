<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
  use HasUuids;

  protected $fillable = [
    'user_id',
    'address',
    'city',
    'province',
    'postal_code',
    'distributor_code',
    'umkm_registration_no',
    'company_name',
    'date_of_birth',
    'gender',
    'extra_data',
  ];

  protected function casts(): array
  {
    return [
      'date_of_birth' => 'date',
      'extra_data'    => 'array',
    ];
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }
}
