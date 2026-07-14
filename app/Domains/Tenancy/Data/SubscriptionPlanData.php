<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Data;

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

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getMaxStudents(): int
    {
        return $this->max_students;
    }

    public function getMaxTeachers(): int
    {
        return $this->max_teachers;
    }

    public function getMaxExamsPerTerm(): int
    {
        return $this->max_exams_per_term;
    }

    public function getPriceMonthly(): ?int
    {
        return $this->price_monthly;
    }

    public function getPriceYearly(): ?int
    {
        return $this->price_yearly;
    }

    public function getFeatures(): ?array
    {
        return $this->features;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
