<?php
// AddCartItemRequest.php
namespace App\Modules\Order\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(['user', 'agen']);
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'uuid', 'exists:product_variants,id'],
            'quantity'           => ['required', 'integer', 'min:1', 'max:99'],
            'notes'              => ['nullable', 'string', 'max:200'],
        ];
    }
}
