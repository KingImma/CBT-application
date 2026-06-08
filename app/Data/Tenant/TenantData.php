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
        public readonly ?string $school_type,
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
}
