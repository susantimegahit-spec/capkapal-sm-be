<?php

namespace App\Modules\Auth\DataTransferObjects;

use App\Modules\Auth\Enums\UserType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\{Email, Max, Min, Confirmed};

class RegisterData extends Data
{
  public function __construct(
    public readonly string   $name,
    public readonly string   $email,
    public readonly string   $password,
    public readonly ?UserType $user_type,
    public readonly ?string  $phone       = null,
    public readonly ?string  $referral_code = null,
    // Profile fields
    public readonly ?string  $company_name          = null,
    public readonly ?string  $distributor_code       = null,
    public readonly ?string  $umkm_registration_no  = null,
  ) {}
}
