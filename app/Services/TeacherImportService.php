<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Tenants\Teacher\TeacherAction;
use App\Data\Results\ImportResult;
use App\Data\Schemas\TeacherImportSchema;
use App\Models\Tenant\User;
use App\Services\Import\CsvImportService;

class TeacherImportService extends CsvImportService
{
    public function __construct(
        private TeacherAction $teacherAction,
    ) {}

    protected function schemaClass(): string
    {
        return TeacherImportSchema::class;
    }

    protected function findDuplicates(array $rows, array &$errors): array
    {
        $resolvedRows = [];
        $identityFields = TeacherImportSchema::IDENTITY;

        foreach ($rows as $row) {
            $data = $row['data'];
            $identifiers = $this->extractIdentifiers($data, $identityFields);

            $existing = User::role('teacher');

            foreach ($identifiers as $key => $value) {
                if ($key === 'email') {
                    $existing->where('email', $value);
                } else {
                    $existing->whereHas('teacherProfile', fn ($q) => $q->where($key, $value));
                }
            }

            $exists = $existing->exists();

            if ($exists) {
                $row['_duplicates'] = [];
                foreach ($identifiers as $key => $value) {
                    $row['_duplicates'][] = ['key' => $key, 'value' => $value];
                }
            }

            $resolvedRows[] = $row;
        }

        return $resolvedRows;
    }

    protected function processRows(array $rows, array $duplicateByRow): ImportResult
    {
        $imported = 0;
        $skipped = 0;
        $updated = 0;

        $duplicateKeys = collect($duplicateByRow)->pluck('row')->toArray();

        foreach ($rows as $rn => $row) {
            $data = $row['data'];
            $email = $data['email'];

            if (in_array($rn, $duplicateKeys)) {
                $skipped++;

                continue;
            }

            $payload = $this->buildPayload($data, $email);
            $this->teacherAction->create($payload);
            $imported++;
        }

        return $this->buildPartsSummary($imported, $skipped, $updated, count($rows));
    }

    private function extractIdentifiers(array $data, array $identityFields): array
    {
        $identifiers = [];
        foreach ($identityFields as $field) {
            if (! empty($data[$field])) {
                $identifiers[$field] = $data[$field];
            }
        }

        return $identifiers;
    }

    private function buildPayload(array $data, string $email): array
    {
        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'qualification' => $data['qualification'] ?? null,
        ];

        if (! empty($data['staff_id'])) {
            $payload['staff_id'] = $data['staff_id'];
        }

        return $payload;
    }
}
