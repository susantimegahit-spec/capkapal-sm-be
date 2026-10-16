<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'min:2', 'max:100'],
            'phone'       => ['sometimes', 'nullable', 'string', 'max:20', 'unique:users,phone,' . $this->user()->id],
            'address'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'province'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender'      => ['sometimes', 'nullable', 'in:male,female,other'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'distributor_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'umkm_registration_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'umkm_registration' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
