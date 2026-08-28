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
            'user_id'         => $this->user_id,
            'nik'             => $this->nik,
            'gender'          => $this->gender,
            'education_level' => $this->education_level,
            'major'           => $this->major,
            'institution'     => $this->institution,
            'expected_salary' => $this->expected_salary,
            'province'        => $this->province,
            'city'            => $this->city,
            'transcript_url'  => $this->transcript_url,
            'certificate_url' => $this->certificate_url,
            'created_at'      => $this->created_at->toISOString(),

            // Relasi (di-load kondisional)
            'career' => $this->whenLoaded('career', fn() => new CareerResource($this->career)),
        ];
    }
}
