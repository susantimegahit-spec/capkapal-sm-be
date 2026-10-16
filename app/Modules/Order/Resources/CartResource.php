<?php
// app/Modules/Order/Resources/CartResource.php
namespace App\Modules\Order\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'item_count'      => $this->item_count,
            'subtotal'        => $this->subtotal,
            'coupon_code'     => $this->coupon_code,
            'coupon_discount' => $this->coupon_discount,
            'points_used'     => $this->points_used,
            'points_discount' => $this->points_discount,
            'total'           => $this->total,
            'items'           => CartItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
