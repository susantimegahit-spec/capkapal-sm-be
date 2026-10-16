<?php
// StoreReviewRequest.php
namespace App\Modules\Product\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }

    public function rules(): array
    {
        return [
            'rating'        => ['required', 'integer', 'min:1', 'max:5'],
            'content'       => ['nullable', 'string', 'max:1000'],
            'order_item_id' => ['nullable', 'uuid', 'exists:order_items,id'],
            'images'        => ['nullable', 'array', 'max:3'],
            'images.*'      => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
