<?php

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id'         => $this->id,
      'name'       => $this->name,
      'email'      => $this->email,
      'phone'      => $this->phone,
      'user_type'  => $this->user_type,
      'status'     => $this->status,
      'avatar_url' => $this->avatar_url,
      'roles'      => $this->getRoleNames(),
      'is_email_verified' => ! is_null($this->email_verified_at),
      'profile'    => $this->whenLoaded('profile', fn() => new UserProfileResource($this->profile)),
      'created_at' => $this->created_at->toISOString(),
    ];
  }
}
