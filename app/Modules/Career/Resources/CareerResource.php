<?php

namespace App\Modules\Career\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'slug'            => $this->slug,
            'description'     => $this->description,
            'requirements'    => $this->requirements,
            'location'        => $this->location,
            'employment_type' => $this->employment_type,
            'status'          => $this->status,
            'published_at'    => $this->published_at?->toISOString(),
            'closed_at'       => $this->closed_at?->toISOString(),
            'created_at'      => $this->created_at->toISOString(),

            // Hanya untuk admin
            'applications_count' => $this->when(
                $this->applications_count !== null,
                $this->applications_count
            ),
            'has_applied' => $this->when(
                auth('sanctum')->check(),
                function () {
                    return $this->applications()->where('user_id', auth('sanctum')->id())->exists();
                }
            ),
        ];
    }
}
