<?php

declare(strict_types=1);

namespace App\Domains\Academic\Data;

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

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function getClassLevelId(): Optional|string
    {
        return $this->class_level_id;
    }

    public function getStudentsCount(): Optional|int
    {
        return $this->students_count;
    }

    public function getClassLevel(): mixed
    {
        return $this->classLevel;
    }

    public function getAssignedTeacher(): mixed
    {
        return $this->assignedTeacher;
    }

    public function getSubjects(): mixed
    {
        return $this->subjects;
    }
}
