<?php
// ProductResource.php
namespace App\Modules\Product\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userType = $request->user()?->user_type?->value ?? 'consumer';

        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'sku'               => $this->sku,
            'short_description' => $this->short_description,
            'base_price'        => $this->base_price,
            'min_price'         => $this->min_price,
            'max_price'         => $this->max_price,
            'status'            => $this->status,
            'is_featured'       => $this->is_featured,
            'in_stock'          => $this->in_stock,
            'rating_avg'        => $this->rating_avg,
            'review_count'      => $this->review_count,
            'total_sold'        => $this->total_sold,
            'primary_image_url' => $this->primary_image_url,

            // Relasi (di-load kondisional)
            'category'  => $this->whenLoaded('category', fn() => new CategoryResource($this->category)),
            'variants'  => $this->whenLoaded('activeVariants', fn() =>
            ProductVariantResource::collection($this->activeVariants)
                ->map(fn($v) => $v->withUserType($userType))
            ),
//            'images'    => $this->whenLoaded('images', fn() =>
//            ProductImageResource::collection($this->images)
//            ),
            'reviews'   => $this->whenLoaded('reviews', fn() =>
            ProductReviewResource::collection($this->reviews)
            ),

            // Hanya untuk admin
            'description' => $this->when(
                $request->routeIs('admin.*'),
                $this->description
            ),
            'meta'        => $this->when(
                $request->routeIs('admin.*'),
                $this->meta
            ),
            'created_at'  => $this->created_at->toISOString(),
        ];
    }
}
