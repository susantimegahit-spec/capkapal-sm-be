<?php

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'address'              => $this->address,
      'city'                 => $this->city,
      'province'             => $this->province,
      'postal_code'          => $this->postal_code,
      'company_name'         => $this->company_name,
      'distributor_code'     => $this->distributor_code,
      'umkm_registration_no' => $this->umkm_registration_no,
      'date_of_birth'        => $this->date_of_birth?->toDateString(),
      'gender'               => $this->gender,
    ];
  }
}
