<?php

namespace App\Modules\Career\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCareerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint — siapapun boleh submit
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'resume'       => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'], // max 5MB
            'cover_letter' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'resume.required' => 'Resume/CV wajib diunggah.',
            'resume.mimes'    => 'Resume harus berformat PDF, DOC, atau DOCX.',
            'resume.max'      => 'Ukuran resume maksimal 5MB.',
        ];
    }
}
