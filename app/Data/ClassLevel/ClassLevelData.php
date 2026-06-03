<?php

declare(strict_types=1);

namespace App\Data\ClassLevel;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

class ClassLevelData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $slug,
        #[Computed]
        public readonly Optional|int $class_arms_count,
        #[Computed]
        public readonly Optional|int $students_count,
        #[WhenLoaded('classArms')]
        public readonly Optional|DataCollection $classArms,
        #[WhenLoaded('subjects')]
        public readonly Optional|DataCollection $subjects,
    ) {}
}
