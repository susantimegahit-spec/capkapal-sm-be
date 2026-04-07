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
      'address'     => ['sometimes', 'string', 'max:255'],
      'city'        => ['sometimes', 'string', 'max:100'],
      'province'    => ['sometimes', 'string', 'max:100'],
      'postal_code' => ['sometimes', 'string', 'max:10'],
      'date_of_birth' => ['sometimes', 'date', 'before:today'],
      'gender'      => ['sometimes', 'in:male,female,other'],
      'company_name' => ['sometimes', 'nullable', 'string', 'max:150'],
    ];
  }
}
