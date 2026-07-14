<?php

declare(strict_types=1);

namespace App\Domains\Academic\Actions;

use App\Domains\Academic\Exceptions\DuplicateSessionNameException;
use App\Domains\Academic\Exceptions\SessionDateRangeOverlapException;
use App\Models\Tenant\AcademicSession;

class CreateSession
{
    public function execute(array $data): AcademicSession
    {
        $this->ensureNameUnique($data['name']);
        $this->ensureNoDateOverlap($data['start_date'], $data['end_date']);

        return AcademicSession::create($data);
    }

    private function ensureNameUnique(string $name): void
    {
        if (AcademicSession::where('name', $name)->exists()) {
            throw new DuplicateSessionNameException($name);
        }
    }

    private function ensureNoDateOverlap(string $startDate, string $endDate): void
    {
        $conflict = AcademicSession::where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->first();

        if ($conflict) {
            throw new SessionDateRangeOverlapException(
                $conflict->name,
                $conflict->start_date->format('Y-m-d'),
                $conflict->end_date->format('Y-m-d'),
            );
        }
    }
}
