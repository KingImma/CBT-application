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
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateStudentData extends Data
{
    public function __construct(
        #[Nullable, StringType, Max(100)]
        public Optional|string $first_name,
        #[Nullable, StringType, Max(100)]
        public Optional|string $last_name,
        #[Nullable, Email, Max(255)]
        public Optional|string|null $email,
        #[Nullable, StringType, Max(20)]
        public Optional|string|null $phone,
        #[Nullable, Uuid, Exists('class_levels', 'id')]
        public Optional|string $class_level_id,
        #[Nullable, Uuid, Exists('class_arms', 'id')]
        public Optional|string $class_arm_id,
        #[Nullable, StringType, Max(50)]
        public Optional|string|null $admission_number,
        #[Nullable, Date]
        public Optional|string|null $date_of_birth,
        #[Nullable, In(['male', 'female'])]
        public Optional|string|null $gender,
        #[Nullable, Email, Max(255)]
        public Optional|string|null $guardian_email,
        #[Nullable, BooleanType]
        public Optional|bool $is_active,
    ) {}

    public function getFirstName(): Optional|string
    {
        return $this->first_name;
    }

    public function getLastName(): Optional|string
    {
        return $this->last_name;
    }

    public function getEmail(): Optional|string|null
    {
        return $this->email;
    }

    public function getPhone(): Optional|string|null
    {
        return $this->phone;
    }

    public function getClassLevelId(): Optional|string
    {
        return $this->class_level_id;
    }

    public function getClassArmId(): Optional|string
    {
        return $this->class_arm_id;
    }

    public function getAdmissionNumber(): Optional|string|null
    {
        return $this->admission_number;
    }

    public function getDateOfBirth(): Optional|string|null
    {
        return $this->date_of_birth;
    }

    public function getGender(): Optional|string|null
    {
        return $this->gender;
    }

    public function getGuardianEmail(): Optional|string|null
    {
        return $this->guardian_email;
    }

    public function isActive(): Optional|bool
    {
        return $this->is_active;
    }
}
