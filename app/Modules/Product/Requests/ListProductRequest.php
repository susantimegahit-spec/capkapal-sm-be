<?php
// ListProductRequest.php
namespace App\Modules\Product\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'search'      => ['nullable', 'string', 'max:100'],
            'sort'        => ['nullable', 'in:latest,price_asc,price_desc,rating,popular,name_asc'],
            'min_price'   => ['nullable', 'numeric', 'min:0'],
            'max_price'   => ['nullable', 'numeric', 'min:0'],
            'in_stock'    => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
