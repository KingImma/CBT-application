<?php

declare(strict_types=1);

namespace App\Domains\Academic\Actions;

use App\Domains\Academic\Exceptions\DuplicateSessionNameException;
use App\Domains\Academic\Exceptions\SessionDateRangeOverlapException;
use App\Models\Tenant\AcademicSession;

class UpdateSession
{
    public function execute(AcademicSession $session, array $data): AcademicSession
    {
        if (isset($data['name']) && $data['name'] !== $session->name) {
            $this->ensureNameUnique($data['name'], $session->id);
        }

        $startDate = $data['start_date'] ?? $session->start_date->format('Y-m-d');
        $endDate = $data['end_date'] ?? $session->end_date->format('Y-m-d');

        $this->ensureNoDateOverlap($startDate, $endDate, $session->id);

        $session->update($data);

        return $session->fresh();
    }

    private function ensureNameUnique(string $name, string $excludeId): void
    {
        if (AcademicSession::where('name', $name)->where('id', '!=', $excludeId)->exists()) {
            throw new DuplicateSessionNameException($name);
        }
    }

    private function ensureNoDateOverlap(string $startDate, string $endDate, string $excludeId): void
    {
        $conflict = AcademicSession::where('id', '!=', $excludeId)
            ->where('start_date', '<=', $endDate)
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
