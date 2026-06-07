<?php

namespace App\Modules\Auth\Models;

use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Enums\UserType;
use App\Modules\Auth\Notifications\ResetPasswordNotification;
use App\Modules\Order\Models\Order;
use App\Modules\Reward\Models\PointTransaction;
use App\Modules\Reward\Models\Redemption;
use App\Modules\Reward\Models\RewardPoint;
use App\Modules\Reward\Models\UserVoucher;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    'points',
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

  public function addresses(): HasMany
  {
    return $this->hasMany(UserAddress::class);
  }

  public function profile(): HasOne
  {
    return $this->hasOne(UserProfile::class);
  }

  public function orders(): HasMany
  {
    return $this->hasMany(Order::class);
  }

  // ── Accessors ─────────────────────────────────────────────────────────────

  public function getAvatarUrlAttribute(): string
  {
    return $this->avatar
      ? \Storage::url($this->avatar)
      : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=1B4F72&color=fff';
  }

  public function rewardPoint(): HasOne
  {
    return $this->hasOne(RewardPoint::class);
  }

  public function redemptions(): HasMany
  {
    return $this->hasMany(Redemption::class);
  }

  public function vouchers(): HasMany
  {
    return $this->hasMany(UserVoucher::class);
  }

  public function pointTransactions(): HasMany
  {
    return $this->hasMany(PointTransaction::class);
  }

  // ── Password Reset ────────────────────────────────────────────────────────

  public function sendPasswordResetNotification($token): void
  {
    $this->notify(new ResetPasswordNotification($token));
  }

  public function isActive(): bool
  {
    return $this->status === UserStatus::Active;
  }
}
