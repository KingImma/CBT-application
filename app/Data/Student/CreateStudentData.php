<?php

declare(strict_types=1);

namespace App\Data\Student;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

class CreateStudentData extends Data
{
    public function __construct(
        #[Required, StringType, Max(100)]
        public string $first_name,
        #[Required, StringType, Max(100)]
        public string $last_name,
        #[Nullable, Email, Max(255)]
        public ?string $email,
        #[Nullable, StringType, Max(20)]
        public ?string $phone,
        #[Required, Uuid, Exists('class_levels', 'id')]
        public string $class_level_id,
        #[Required, Uuid, Exists('class_arms', 'id')]
        public string $class_arm_id,
        #[Nullable, StringType, Max(50)]
        public ?string $admission_number,
        #[Nullable, Date]
        public ?string $date_of_birth,
        #[Nullable, In(['male', 'female'])]
        public ?string $gender,
        #[Nullable, Email, Max(255)]
        public ?string $guardian_email,
        #[Nullable, BooleanType]
        public ?bool $is_active,
    ) {}

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

    public function getClassLevelId(): string
    {
        return $this->class_level_id;
    }

    public function getClassArmId(): string
    {
        return $this->class_arm_id;
    }

    public function getAdmissionNumber(): ?string
    {
        return $this->admission_number;
    }

    public function getDateOfBirth(): ?string
    {
        return $this->date_of_birth;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getGuardianEmail(): ?string
    {
        return $this->guardian_email;
    }

    public function isActive(): ?bool
    {
        return $this->is_active;
    }
}
