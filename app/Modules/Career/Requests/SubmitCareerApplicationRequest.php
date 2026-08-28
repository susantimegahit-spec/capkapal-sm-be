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
        $user = $this->user('sanctum');
        $hasProfileResume = $user && $user->applicantProfile && $user->applicantProfile->cv_path;

        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'resume'       => [$hasProfileResume ? 'nullable' : 'required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'nik'          => ['nullable', 'string', 'size:16'],
            'gender'       => ['nullable', 'string', 'max:20'],
            'education_level' => ['nullable', 'string', 'max:50'],
            'major'        => ['nullable', 'string', 'max:255'],
            'institution'  => ['nullable', 'string', 'max:255'],
            'expected_salary' => ['nullable', 'numeric'],
            'province'     => ['nullable', 'string', 'max:100'],
            'city'         => ['nullable', 'string', 'max:100'],
            'transcript'   => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'certificate'  => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'resume.required' => 'Resume/CV wajib diunggah.',
            'resume.mimes'    => 'Resume harus berformat PDF, DOC, atau DOCX.',
            'resume.max'      => 'Ukuran resume maksimal 5MB.',
            'transcript.mimes' => 'Transkrip harus berformat PDF, DOC, atau DOCX.',
            'transcript.max' => 'Ukuran transkrip maksimal 5MB.',
            'certificate.mimes' => 'Ijazah harus berformat PDF, DOC, atau DOCX.',
            'certificate.max' => 'Ukuran ijazah maksimal 5MB.',
        ];
    }
}
