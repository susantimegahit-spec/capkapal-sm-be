<?php
// app/Modules/Order/Resources/OrderResource.php
namespace App\Modules\Order\Resources;

use App\Modules\Order\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'order_number'     => $this->order_number,
            'status'           => $this->status,
            'status_label'     => $this->status->label(),
            'subtotal'         => $this->subtotal,
            'coupon_discount'  => $this->coupon_discount,
            'points_discount'  => $this->points_discount,
            'shipping_fee'     => $this->shipping_fee,
            'total'            => $this->total,
            'coupon_code'      => $this->coupon_code,
            'points_used'      => $this->points_used,
            'points_earned'    => $this->points_earned,
            'shipping_courier' => $this->shipping_courier,
            'shipping_service' => $this->shipping_service,
            'tracking_number'  => $this->tracking_number,
            'estimated_delivery' => $this->estimated_delivery?->toDateString(),
            'customer_notes'   => $this->customer_notes,
            'items'            => OrderItemResource::collection($this->whenLoaded('items')),
            'payment'          => $this->whenLoaded('payment'),
            'shipping_address' => new ShippingAddressResource($this->whenLoaded('shippingAddress')),
            'timeline'         => $this->whenLoaded('statusHistories', fn() =>
            $this->statusHistories->map(fn($h) => [
                'status'     => $h->status,
                'label'      => OrderStatus::from($h->status)->label(),
                'notes'      => $h->notes,
                'at'         => $h->created_at->toISOString(),
            ])
            ),
            'created_at'       => $this->created_at->toISOString(),
        ];
    }
}
