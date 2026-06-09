<?php

declare(strict_types=1);

namespace App\Data\Tenant;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateTenantData extends Data
{
    public function __construct(
        #[Nullable, StringType, Max(255)]
        public Optional|string $name,
        #[Nullable, Email, Max(255)]
        public Optional|string|null $email,
        #[Nullable, StringType, Max(500)]
        public Optional|string|null $address,
        #[Nullable, StringType, Max(255)]
        public Optional|string|null $city,
        #[Nullable, StringType, Max(255)]
        public Optional|string|null $state,
    ) {}
}
