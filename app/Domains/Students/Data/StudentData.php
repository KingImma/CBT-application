<?php

declare(strict_types=1);

namespace App\Domains\Students\Data;

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
}
