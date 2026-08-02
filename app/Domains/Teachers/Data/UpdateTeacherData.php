<?php

declare(strict_types=1);

namespace App\Domains\Teachers\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateTeacherData extends Data
{
    public function __construct(
        #[Nullable, StringType, Max(100)]
        public Optional|string $first_name,
        #[Nullable, StringType, Max(100)]
        public Optional|string $last_name,
        #[Nullable, Email, Max(255)]
        public Optional|string $email,
        #[Nullable, StringType, Max(20)]
        public Optional|string|null $phone,
        #[Nullable, In(['male','female','other'])]
        public Optional|string|null $gender,
        #[Nullable, StringType, Max(255)]
        public Optional|string|null $qualification,
        #[Nullable, StringType, Max(50)]
        public Optional|string|null $staff_id,
        #[Nullable, Uuid, Exists('class_levels', 'id')]
        public Optional|string|null $class_level_id,
    ) {}
}
