<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Auth\Rules\TurnstileRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'email'       => ['required', 'email'],
      'password'    => ['required', 'string'],
      'device_name' => ['nullable', 'string', 'max:100'],
      'cf_turnstile_response' => ['required', 'string', new TurnstileRule()],
    ];
  }
}