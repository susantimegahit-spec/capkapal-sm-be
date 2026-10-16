<?php
// ProductReviewResource.php
namespace App\Modules\Product\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'rating'               => $this->rating,
            'content'              => $this->content,
            'images'               => $this->images ?? [],
            'is_verified_purchase' => $this->is_verified_purchase,
            'user' => [
                'name'       => $this->user?->name,
                'avatar_url' => $this->user?->avatar_url,
            ],
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
