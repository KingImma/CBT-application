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

    public function getClassArmsCount(): Optional|int
    {
        return $this->class_arms_count;
    }

    public function getStudentsCount(): Optional|int
    {
        return $this->students_count;
    }

    public function getClassArms(): Optional|DataCollection
    {
        return $this->classArms;
    }

    public function getSubjects(): Optional|DataCollection
    {
        return $this->subjects;
    }
}
