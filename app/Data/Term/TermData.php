<?php

declare(strict_types=1);

namespace App\Data\Term;

use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

class TermData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly bool $is_current,
        public readonly ?string $start_date,
        public readonly ?string $end_date,
        public readonly Optional|string $academic_session_id,
    ) {}
}
