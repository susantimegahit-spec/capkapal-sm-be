<?php

namespace App\Modules\Career\Requests;

use App\Modules\Career\Enums\{CareerStatus, EmploymentType};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateCareerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(['admin', 'editor']);
    }

    public function rules(): array
    {
        return [
            'title'           => ['sometimes', 'string', 'max:255'],
            'description'     => ['sometimes', 'string'],
            'requirements'    => ['nullable', 'string'],
            'location'        => ['nullable', 'string', 'max:255'],
            'employment_type' => ['sometimes', new Enum(EmploymentType::class)],
            'status'          => ['sometimes', new Enum(CareerStatus::class)],
        ];
    }
}
