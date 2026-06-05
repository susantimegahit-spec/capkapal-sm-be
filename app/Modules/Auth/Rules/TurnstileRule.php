<?php

namespace App\Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class TurnstileRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secretKey = env('TURNSTILE_SECRET_KEY', '1x0000000000000000000000000000000AA');

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secretKey,
            'response' => $value,
        ]);

        if ($response->json('success') !== true) {
            $fail('Verifikasi CAPTCHA gagal. Silakan coba lagi.');
        }
    }
}
