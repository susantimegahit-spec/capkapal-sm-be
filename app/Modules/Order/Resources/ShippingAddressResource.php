<?php
// app/Modules/Order/Resources/ShippingAddressResource.php
namespace App\Modules\Order\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'recipient_name' => $this->recipient_name,
            'phone'          => $this->phone,
            'address'        => $this->address,
            'district'       => $this->district,
            'city'           => $this->city,
            'province'       => $this->province,
            'postal_code'    => $this->postal_code,
            'village'        => $this->village
        ];
    }
}
