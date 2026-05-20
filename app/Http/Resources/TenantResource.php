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
            // Core Identity
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'handle' => $this->handle,
            'school_type' => $this->school_type,
            'logo' => $this->logo,
            'is_active' => $this->is_active,

            // Contact Info (Grouped for a cleaner frontend state)
            'contact' => [
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
                'city' => $this->city,
                'state' => $this->state,
            ],

            // Subscription & Plan Info
            'subscription' => [
                'status' => $this->subscription_status,
                // We pull the relationship here
                'plan' => $this->whenLoaded('plan', fn () => [
                    'id' => $this->plan->id,
                    'name' => $this->plan->name,
                ]),
                'trial_ends_at' => $this->trial_ends_at,
                'ends_at' => $this->subscription_ends_at,
            ],

            // Relationships
            'domains' => $this->whenLoaded('domains', fn () => $this->domains->map(fn ($domain) => [
                'id' => $domain->id,
                'domain' => $domain->domain,
            ])
            ),

            // Timestamps
            'onboarding_completed_at' => $this->onboarding_completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
