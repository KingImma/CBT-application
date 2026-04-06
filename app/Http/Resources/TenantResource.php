<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public static $wrap = null;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domains' => $this->whenLoaded('domains'),
            'contact' => [
                'email' => $this->email,
                'phone' => $this->phone
            ],
            'location' => [
                'address' => $this->address,
                'city'    => $this->city,
                'state'   => $this->state
            ],
            'subcription_status' => $this->subscription_status,
            'is_active'          => $this->is_active,
            'created_at'         => $this->created_at?->toIso8601String()
        ];
    }
}
