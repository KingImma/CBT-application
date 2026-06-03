<?php

declare(strict_types=1);

namespace App\Data\AcademicSession;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class AcademicSessionData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly bool $is_current,
        public readonly ?string $start_date,
        public readonly ?string $end_date,
        #[WhenLoaded('terms')]
        public readonly mixed $terms,
    ) {}
}
