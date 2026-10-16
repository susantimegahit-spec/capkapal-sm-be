<?php
// ProductVariantResource.php
namespace App\Modules\Product\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    private string $userType = 'consumer';

    public function withUserType(string $userType): static
    {
        $this->userType = $userType;
        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id'         => $this->id,
            'name'       => $this->name,
            'sku'        => $this->sku,
            'price'      => $this->getPriceForUserType($this->userType),
            'stock'      => $this->stock,
            'weight'     => $this->weight,
            'attributes' => $this->attributes,
            'is_active'  => $this->is_active,
            'is_low_stock'   => $this->isLowStock(),
            'is_out_of_stock' => $this->isOutOfStock(),
        ];

        if ($request->routeIs('admin.*')) {
            // For admin edit pages, return the raw database prices
            $data['price'] = $this->price;
            $data['price_distributor'] = $this->price_distributor;
        }

        return $data;
    }
}
