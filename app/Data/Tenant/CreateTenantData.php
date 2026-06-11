<?php

declare(strict_types=1);

namespace App\Data\Tenant;

use Spatie\LaravelData\Attributes\Validation\AlphaDash;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class CreateTenantData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,

        #[Required, StringType, Max(63), AlphaDash, Rule(['unique:tenants,id', 'unique:tenants,handle'])]
        public string $handle,

        #[Required, Email, Max(255)]
        public string $admin_email,

        #[Required, StringType]
        public string $admin_password,

        #[Required, StringType, Max(255)]
        public string $admin_first_name,

        #[Required, StringType, Max(255)]
        public string $admin_last_name,

        #[Required, StringType, Max(20)]
        public string $admin_phone,

        #[Nullable, StringType, Max(500)]
        public ?string $address,

        #[Nullable, StringType, Max(255)]
        public ?string $city,

        #[Nullable, StringType, Max(255)]
        public ?string $state,

        #[Nullable, Exists('subscription_plans', 'id')]
        public ?string $plan_id,

        public array $curriculum = [],
    ) {}

    /**
     * Runs before validation. Morphs the raw HTTP payload (camelCase frontend
     * field names) into the snake_case domain shape this class expects.
     */
    public static function prepareForPipeline(array $properties): array
    {
        // Only remap if the payload comes from the frontend onboarding form.
        if (! isset($properties['schoolName'])) {
            return $properties;
        }

        $nameParts = explode(' ', $properties['fullName'] ?? '', 2);

        return [
            'name' => $properties['schoolName'] ?? '',
            'handle' => $properties['handle'] ?? '',
            'admin_email' => $properties['email'] ?? '',
            'admin_password' => $properties['password'] ?? '',
            'admin_first_name' => $nameParts[0] ?? '',
            'admin_last_name' => $nameParts[1] ?? '',
            'admin_phone' => $properties['phone'] ?? '',
            'address' => $properties['address'] ?? null,
            'state' => $properties['state'] ?? null,
            'city' => $properties['city'] ?? null,
            'plan_id' => $properties['plan_id'] ?? null,
            'curriculum' => [],
        ];
    }
}
