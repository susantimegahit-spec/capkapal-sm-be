<?php
// StoreProductRequest.php
namespace App\Modules\Product\Requests;

use App\Modules\Product\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(['admin', 'editor']);
    }

    public function rules(): array
    {
        return [
            'category_id'       => ['required', 'uuid', 'exists:categories,id'],
            'name'              => ['required', 'string', 'max:255'],
            'sku'               => ['required', 'string', 'max:50', 'unique:products,sku'],
            'description'       => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'base_price'        => ['required', 'numeric', 'min:0'],
            'status'            => ['required', new Enum(ProductStatus::class)],
            'is_featured'       => ['boolean'],
            'meta'              => ['nullable', 'array'],
            'meta.seo_title'    => ['nullable', 'string', 'max:70'],
            'meta.seo_description' => ['nullable', 'string', 'max:160'],
            'primary_image_url' => ['nullable', 'string', 'max:2048'],

            // Variants (wajib minimal 1)
            'variants'               => ['required', 'array', 'min:1'],
            'variants.*.name'        => ['required', 'string', 'max:100'],
            'variants.*.sku'         => ['required', 'string', 'max:50', 'distinct'],
            'variants.*.price'       => ['required', 'numeric', 'min:0'],
            'variants.*.stock'       => ['required', 'integer', 'min:0'],
            'variants.*.weight'      => ['nullable', 'numeric', 'min:0'],
            'variants.*.price_distributor' => ['nullable', 'numeric', 'min:0'],
            'variants.*.price_umkm'        => ['nullable', 'numeric', 'min:0'],
            'variants.*.attributes'        => ['nullable', 'array'],

            // Images
            'images'          => ['nullable', 'array', 'max:8'],
            'images.*'        => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
