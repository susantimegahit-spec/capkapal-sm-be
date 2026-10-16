<?php

namespace App\Modules\Career\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ApplicantProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'phone'         => $this->phone,
            'address'       => $this->address,
            'linkedin_url'  => $this->linkedin_url,
            'portfolio_url' => $this->portfolio_url,
            'cv_path'       => $this->cv_path,
            'cv_url'        => $this->cv_path ? Storage::disk('public')->url($this->cv_path) : null,
            'education_level' => $this->education_level,
            'institution'   => $this->institution,
            'major'         => $this->major,
            'nik'           => $this->nik,
            'province'      => $this->province,
            'city'          => $this->city,
            'gender'        => $this->gender,
            'expected_salary' => $this->expected_salary ? (int)$this->expected_salary : null,
            'transcript_path' => $this->transcript_path,
            'transcript_url' => $this->transcript_path ? Storage::disk('public')->url($this->transcript_path) : null,
            'certificate_path' => $this->certificate_path,
            'certificate_url' => $this->certificate_path ? Storage::disk('public')->url($this->certificate_path) : null,
            'created_at'    => $this->created_at->toISOString(),
        ];
    }
}
