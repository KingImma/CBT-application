<?php

declare(strict_types=1);

namespace App\Domains\Import\Actions;

use App\Domains\Import\Data\ImportResult;
use App\Domains\Import\Data\Schemas\TeacherImportSchema;
use App\Enums\RoleType;
use App\Models\Tenant\User;

class ImportTeachers extends CsvImport
{
    public function __construct(private Teacher $teacher) {}

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

            $existing = User::role(RoleType::Teacher->value);

            foreach ($identifiers as $key => $value) {
                if ($key === 'email') {
                    $existing->where('email', $value);
                } else {
                    $existing->whereHas(
                        'teacherProfile',
                        fn ($q) => $q->where($key, $value),
                    );
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

    protected function processRows(
        array $rows,
        array $duplicateByRow,
    ): ImportResult {
        $imported = 0;
        $skipped = 0;
        $updated = 0;

        $duplicateKeys = collect($duplicateByRow)->pluck('row')->toArray();

        foreach ($rows as $rn => $row) {
            $data = $row['data'];
            $email = $data['email'];

            if (in_array($rn, $duplicateKeys)) {
                if ($this->overwriteExisting) {
                    $existingUser = User::role(RoleType::Teacher->value)
                        ->where('email', $email)
                        ->first();

                    if ($existingUser) {
                        $existingUser->update([
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'phone' => $data['phone'] ?? $existingUser->phone,
                        ]);

                        if ($existingUser->teacherProfile) {
                            $existingUser->teacherProfile->update([
                                'qualification' => $data['qualification'] ?? $existingUser->teacherProfile->qualification,
                                'staff_id' => $data['staff_id'] ?? $existingUser->teacherProfile->staff_id,
                            ]);
                        }

                        $updated++;
                    }

                    continue;
                }

                $skipped++;

                continue;
            }

            $payload = $this->buildPayload($data, $email);
            $this->teacher->create($payload);
            $imported++;
        }

        return $this->buildPartsSummary($imported, $skipped, $updated, count($rows));
    }

    private function extractIdentifiers(
        array $data,
        array $identityFields,
    ): array {
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
