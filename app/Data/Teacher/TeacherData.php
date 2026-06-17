<?php

declare(strict_types=1);

namespace App\Data\Teacher;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class TeacherData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly bool $is_active,
        #[WhenLoaded('teacherProfile')]
        public readonly mixed $teacherProfile,
        #[WhenLoaded('assignedClasses')]
        public readonly mixed $assignedClasses,
        #[WhenLoaded('teacherAssignments')]
        public readonly mixed $teacherAssignments,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->first_name;
    }

    public function getLastName(): string
    {
        return $this->last_name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getTeacherProfile(): mixed
    {
        return $this->teacherProfile;
    }

    public function getAssignedClasses(): mixed
    {
        return $this->assignedClasses;
    }

    public function getTeacherAssignments(): mixed
    {
        return $this->teacherAssignments;
    }
}
