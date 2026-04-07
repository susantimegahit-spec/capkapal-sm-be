<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Auth\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\{Enum, Password};

class RegisterRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name'      => ['required', 'string', 'min:2', 'max:100'],
      'email'     => ['required', 'email:rfc,dns', 'unique:users,email', 'max:150'],
      'phone'     => ['nullable', 'string', 'max:20', 'unique:users,phone'],
      'password'  => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
      'user_type' => ['nullable', new Enum(UserType::class)],

      // Conditional: wajib jika distributor
      'distributor_code'     => ['required_if:user_type,distributor', 'nullable', 'string', 'max:30'],
      'umkm_registration_no' => ['required_if:user_type,umkm', 'nullable', 'string', 'max:50'],
      'company_name'         => ['nullable', 'string', 'max:150'],
      'referral_code'        => ['nullable', 'string', 'size:8', 'exists:users,referral_code'],
    ];
  }

  public function messages(): array
  {
    return [
      'name.required'              => 'Nama wajib diisi.',
      'email.required'             => 'Email wajib diisi.',
      'email.unique'               => 'Email sudah terdaftar.',
      'phone.unique'               => 'Nomor telepon sudah terdaftar.',
      'password.required'          => 'Password wajib diisi.',
      'password.confirmed'         => 'Konfirmasi password tidak sesuai.',
      'password.min'               => 'Password minimal 8 karakter.',
      'password.letters'           => 'Password harus mengandung setidaknya satu huruf.',
      'distributor_code.required_if' => 'Kode distributor wajib diisi.',
      'umkm_registration_no.required_if' => 'Nomor registrasi UMKM wajib diisi.',
      'referral_code.exists'       => 'Kode referral tidak valid.',
    ];
  }
}
