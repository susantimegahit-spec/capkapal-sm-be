<?php
// app/Modules/Order/Resources/OrderItemResource.php
namespace App\Modules\Order\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'product_name' => $this->product_name,
            'variant_name' => $this->variant_name,
            'sku'          => $this->sku,
            'unit_price'   => $this->unit_price,
            'quantity'     => $this->quantity,
            'subtotal'     => $this->subtotal,
            'image_url'    => $this->product_snapshot['image_url'] ?? null,
        ];
    }
}
