<?php

declare(strict_types=1);

namespace App\Data\SubscriptionPlan;

use Spatie\LaravelData\Resource;

class SubscriptionPlanData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $slug,
        public readonly int $max_students,
        public readonly int $max_teachers,
        public readonly int $max_exams_per_term,
        public readonly ?int $price_monthly,
        public readonly ?int $price_yearly,
        public readonly ?array $features,
        public readonly bool $is_active,
    ) {}
}
