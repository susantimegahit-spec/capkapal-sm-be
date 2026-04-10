<?php
// app/Modules/Order/Resources/CartItemResource.php
namespace App\Modules\Order\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variant = $this->variant;
        $product = $variant?->product;

        return [
            'id'       => $this->id,
            'quantity' => $this->quantity,
            'unit_price'=> $this->unit_price,
            'subtotal'  => $this->subtotal,
            'notes'     => $this->notes,
            'variant' => [
                'id'         => $variant?->id,
                'name'       => $variant?->name,
                'sku'        => $variant?->sku,
                'stock'      => $variant?->stock,
                'attributes' => $variant?->attributes,
            ],
            'product' => [
                'id'         => $product?->id,
                'name'       => $product?->name,
                'slug'       => $product?->slug,
                'image_url'  => $product?->primary_image_url,
            ],
        ];
    }
}
