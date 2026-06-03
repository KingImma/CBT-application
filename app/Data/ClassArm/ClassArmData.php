<?php

declare(strict_types=1);

namespace App\Data\ClassArm;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

class ClassArmData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?int $capacity,
        #[Computed]
        public readonly Optional|string $class_level_id,
        #[Computed]
        public readonly Optional|int $students_count,
        #[WhenLoaded('classLevel')]
        public readonly mixed $classLevel,
        #[WhenLoaded('assignedTeacher')]
        public readonly mixed $assignedTeacher,
        #[WhenLoaded('subjects')]
        public readonly mixed $subjects,
    ) {}
}
