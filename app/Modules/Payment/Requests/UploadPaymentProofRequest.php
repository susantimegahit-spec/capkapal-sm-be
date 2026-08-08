<?php

namespace App\Modules\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['user', 'agen']);
    }

    public function rules(): array
    {
        return [
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // max 5MB
        ];
    }

    public function messages(): array
    {
        return [
            'payment_proof.required' => 'Bukti pembayaran wajib diunggah.',
            'payment_proof.image'    => 'File harus berupa gambar.',
            'payment_proof.mimes'    => 'Format gambar yang diterima: JPG, JPEG, PNG, atau WebP.',
            'payment_proof.max'      => 'Ukuran gambar maksimal 5MB.',
        ];
    }
}
