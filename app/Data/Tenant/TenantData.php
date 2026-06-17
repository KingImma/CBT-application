<?php

declare(strict_types=1);

namespace App\Data\Tenant;

use App\Enums\StatusType;
use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class TenantData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $handle,
        public readonly ?string $logo,
        public readonly bool $is_active,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $address,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?StatusType $subscription_status,
        public readonly ?string $trial_ends_at,
        public readonly ?string $subscription_ends_at,
        public readonly ?string $onboarding_completed_at,
        public readonly ?string $created_at,
        #[WhenLoaded('plan')]
        public readonly mixed $plan,
        #[WhenLoaded('domains')]
        public readonly mixed $domains,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function getSubscriptionStatus(): ?StatusType
    {
        return $this->subscription_status;
    }

    public function getTrialEndsAt(): ?string
    {
        return $this->trial_ends_at;
    }

    public function getSubscriptionEndsAt(): ?string
    {
        return $this->subscription_ends_at;
    }

    public function getOnboardingCompletedAt(): ?string
    {
        return $this->onboarding_completed_at;
    }

    public function getCreatedAt(): ?string
    {
        return $this->created_at;
    }

    public function getPlan(): mixed
    {
        return $this->plan;
    }

    public function getDomains(): mixed
    {
        return $this->domains;
    }
}
