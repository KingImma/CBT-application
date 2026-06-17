<?php

declare(strict_types=1);

namespace App\Data\Subject;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

class SubjectData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $code,
        #[Computed]
        public readonly Optional|string $category,
        #[Computed]
        public readonly Optional|string $department,
        public readonly bool $is_active,
        #[WhenLoaded('classLevels')]
        public readonly mixed $classLevels,
        #[WhenLoaded('teacherAssignments')]
        public readonly mixed $teacherAssignments,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getCategory(): Optional|string
    {
        return $this->category;
    }

    public function getDepartment(): Optional|string
    {
        return $this->department;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getClassLevels(): mixed
    {
        return $this->classLevels;
    }

    public function getTeacherAssignments(): mixed
    {
        return $this->teacherAssignments;
    }
}
