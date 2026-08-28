<?php

namespace App\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Modules\Order\Resources\OrderResource;

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
      'points' => (int) $this->points,
      'is_email_verified' => ! is_null($this->email_verified_at),
      'profile'    => $this->whenLoaded('profile', fn() => new UserProfileResource($this->profile)),
      'applicant_profile' => $this->whenLoaded('applicantProfile', fn() => new \App\Modules\Career\Resources\ApplicantProfileResource($this->applicantProfile)),
      'orders_count' => $this->whenHas('orders_count'),
      'orders_sum_total' => $this->whenHas('orders_sum_total', fn() => (float) $this->orders_sum_total),
      'successful_orders_count' => $this->whenHas('successful_orders_count'),
      'successful_orders_total' => $this->whenHas('successful_orders_total', fn() => (float) $this->successful_orders_total),
      'orders'     => $this->whenLoaded('orders', fn() => OrderResource::collection($this->orders)),
      'created_at' => $this->created_at->toISOString(),
    ];
  }
}
