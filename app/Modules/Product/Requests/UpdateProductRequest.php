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
            'category_id'                   => ['sometimes', 'uuid', 'exists:categories,id'],
            'name'                          => ['sometimes', 'string', 'max:255'],
            'sku'                           => ['sometimes', 'string', 'max:50', "unique:products,sku,{$productId}"],
            'description'                   => ['nullable', 'string'],
            'short_description'             => ['nullable', 'string', 'max:500'],
            'base_price'                    => ['sometimes', 'numeric', 'min:0'],
            'status'                        => ['sometimes', new Enum(ProductStatus::class)],
            'is_featured'                   => ['boolean'],
            'meta'                          => ['nullable', 'array'],
            'primary_image_url'             => ['nullable', 'string', 'max:2048'],

            // Tambahkan di dalam return [ ... ];
            'variants'                      => ['sometimes', 'array', 'min:1'],
            'variants.*.id'                 => ['nullable', 'uuid'], // INI SANGAT PENTING
            'variants.*.name'               => ['required_with:variants', 'string', 'max:255'],
            'variants.*.sku'                => ['required_with:variants', 'string', 'max:50', 'distinct'],
            'variants.*.price'              => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.stock'              => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.weight'             => ['nullable', 'numeric', 'min:0'],
            'variants.*.price_distributor'  => ['nullable', 'numeric', 'min:0'],
            'variants.*.attributes'         => ['nullable', 'array'],
        ];
    }
}