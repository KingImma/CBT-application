<?php

declare(strict_types=1);

namespace App\Data\Student;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class StudentData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly bool $is_active,
        #[WhenLoaded('studentProfile')]
        public readonly mixed $studentProfile,
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

    public function getStudentProfile(): mixed
    {
        return $this->studentProfile;
    }
}
