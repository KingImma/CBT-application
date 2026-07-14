<?php

declare(strict_types=1);

namespace App\Domains\Academic\Data;

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

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isCurrent(): bool
    {
        return $this->is_current;
    }

    public function getStartDate(): ?string
    {
        return $this->start_date;
    }

    public function getEndDate(): ?string
    {
        return $this->end_date;
    }

    public function getAcademicSessionId(): Optional|string
    {
        return $this->academic_session_id;
    }
}
