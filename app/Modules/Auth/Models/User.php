<?php

namespace App\Modules\Auth\Models;

use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Enums\UserType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
  use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

  protected string $guard_name = 'api';
  public $incrementing = false;
  protected $keyType = 'string';

  protected $fillable = [
      'id',
      'name',
      'email',
      'phone',
      'password',
      'user_type',
      'status',
      'avatar',
      'referral_code',
      'referred_by',
      'email_verified_at',
  ];

  protected $hidden = [
    'password',
    'remember_token',
  ];

  protected function casts(): array
  {
    return [
      'email_verified_at' => 'datetime',
      'password'          => 'hashed',
      'user_type'         => UserType::class,   // enum
      'status'            => UserStatus::class, // enum
    ];
  }

  // ── Relationships ─────────────────────────────────────────────────────────

  public function profile(): HasOne
  {
    return $this->hasOne(UserProfile::class);
  }

  // ── Accessors ─────────────────────────────────────────────────────────────

  public function getAvatarUrlAttribute(): string
  {
    return $this->avatar
      ? \Storage::url($this->avatar)
      : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=1B4F72&color=fff';
  }

  public function isActive(): bool
  {
    return $this->status === UserStatus::Active;
  }
}
