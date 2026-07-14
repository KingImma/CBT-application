<?php

declare(strict_types=1);

namespace App\Domains\Teachers\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

class CreateTeacherData extends Data
{
    public function __construct(
        #[Required, StringType, Max(100)]
        public string $first_name,
        #[Required, StringType, Max(100)]
        public string $last_name,
        #[Required, Email, Max(255), Unique('users', 'email')]
        public string $email,
        #[Nullable, StringType, Max(20)]
        public ?string $phone,
        #[Nullable, StringType, Max(255)]
        public ?string $qualification,
        #[Nullable, StringType, Max(50), Unique('users', 'staff_id')]
        public ?string $staff_id,
        #[Nullable, Uuid, Exists('class_levels', 'id')]
        public ?string $class_level_id,
    ) {}
}
