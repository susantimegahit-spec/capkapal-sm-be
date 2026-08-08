<?php

namespace App\Modules\Career\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareerApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'career_id'    => $this->career_id,
            'name'         => $this->name,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'resume_url'   => $this->resume_url,
            'cover_letter' => $this->cover_letter,
            'status'       => $this->status,
            'admin_notes'  => $this->admin_notes,
            'created_at'   => $this->created_at->toISOString(),

            // Relasi (di-load kondisional)
            'career' => $this->whenLoaded('career', fn() => new CareerResource($this->career)),
        ];
    }
}
