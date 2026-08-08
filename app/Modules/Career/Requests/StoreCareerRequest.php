<?php

namespace App\Modules\Career\Requests;

use App\Modules\Career\Enums\{CareerStatus, EmploymentType};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreCareerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(['admin', 'editor']);
    }

    public function rules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['required', 'string'],
            'requirements'    => ['nullable', 'string'],
            'location'        => ['nullable', 'string', 'max:255'],
            'employment_type' => ['required', new Enum(EmploymentType::class)],
            'status'          => ['required', new Enum(CareerStatus::class)],
        ];
    }
}
