<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserAddressRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()->hasRole(['user', 'agen']);
  }

  public function rules(): array
  {
    return [
      'label'          => ['required', 'string', 'max:50'],
      'recipient_name' => ['required', 'string', 'max:100'],
      'phone'          => ['required', 'string', 'max:20'],
      'address'        => ['required', 'string', 'max:500'],
      'postal_code'    => ['required', 'string', 'max:10'],
      'district'       => ['nullable', 'string', 'max:100'],
      'village'       => ['nullable', 'string', 'max:100'],

      'province' => ['required', 'string', 'max:100', Rule::in(['Jawa Timur'])],
      'city'     => ['required', 'string', 'max:100', Rule::in(['Kota Surabaya', 'Surabaya', 'Kabupaten Gresik', 'Gresik'])],

      'is_primary'     => ['nullable', 'boolean'],
    ];
  }

  public function messages(): array
  {
    return [
      'province.in' => 'Maaf, layanan kami saat ini hanya tersedia untuk wilayah Jawa Timur.',
      'city.in'     => 'Maaf, alamat Anda harus berada di area Surabaya atau Gresik.',
    ];
  }
}
