<?php
// UpdateProductRequest.php
namespace App\Modules\Product\Requests;

use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(['admin', 'editor']);
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'category_id'       => ['sometimes', 'uuid', 'exists:categories,id'],
            'name'              => ['sometimes', 'string', 'max:255'],
            'sku'               => ['sometimes', 'string', 'max:50', "unique:products,sku,{$productId}"],
            'description'       => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'base_price'        => ['sometimes', 'numeric', 'min:0'],
            'status'            => ['sometimes', new Enum(ProductStatus::class)],
            'is_featured'       => ['boolean'],
            'meta'              => ['nullable', 'array'],
        ];
    }
}
