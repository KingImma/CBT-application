<?php

declare(strict_types=1);

namespace App\Domains\Import\Actions;

use App\Domains\Import\Data\ImportResult;
use App\Domains\Import\Data\Schemas\TeacherImportSchema;
use App\Domains\Teachers\Actions\TeacherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Enums\RoleType;
use App\Models\Tenant\User;

class ImportTeachers extends CsvImport
{
    public function __construct(private TeacherService $teacher) {}

    protected function schemaClass(): string
    {
        return TeacherImportSchema::class;
    }

    protected function findDuplicates(array $rows, array &$errors): array
    {
        $resolvedRows = [];
        $identityFields = TeacherImportSchema::IDENTITY;
    
        foreach ($rows as $rowNumber => $row) {
            $data = $row['data'];
            $identifiers = $this->extractIdentifiers($data, $identityFields);
    
            $existing = User::role(RoleType::Teacher->value);
    
            foreach ($identifiers as $key => $value) {
                if ($key === 'email') {
                    $existing->where('email', $value);
                } else {
                    $existing->whereHas(
                        'teacherProfile',
                        fn ($query) => $query->where($key, $value),
                    );
                }
            }
    
            if ($existing->exists()) {
                $row['_duplicates'] = array_map(
                    static fn (string $key, mixed $value): array => [
                        'key' => $key,
                        'value' => $value,
                    ],
                    array_keys($identifiers),
                    array_values($identifiers),
                );
            }
    
            $resolvedRows[$rowNumber] = $row;
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
    
        $duplicateKeys = collect($duplicateByRow)
            ->pluck('row')
            ->unique()
            ->map(fn ($row) => (int) $row)
            ->all();
    
        foreach ($rows as $index => $row) {
            $data = $row['data'];
            $email = $data['email'];
            $rowNumber = (int) ($row['row'] ?? $index);
    
            try {
                if (in_array($rowNumber, $duplicateKeys, true)) {
                    if (! $this->overwriteExisting) {
                        $skipped++;
    
                        continue;
                    }
    
                    DB::connection('tenant')->transaction(function () use ($data, $email) {
                        $existingUser = User::role(RoleType::Teacher->value)
                            ->where('email', $email)
                            ->first();
    
                        if (! $existingUser) {
                            return;
                        }
    
                        $existingUser->update([
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'phone' => $data['phone']
                                ?? $existingUser->phone,
                        ]);
    
                        if ($existingUser->teacherProfile) {
                            $existingUser->teacherProfile->update([
                                'qualification' => $data['qualification']
                                    ?? $existingUser->teacherProfile->qualification,
                                'staff_id' => $data['staff_id']
                                    ?? $existingUser->teacherProfile->staff_id,
                            ]);
                        }
                    }, 3);
    
                    $updated++;
    
                    continue;
                }
    
                DB::connection('tenant')->transaction(function () use ($data, $email) {
                    $this->teacher->create(
                        $this->buildPayload($data, $email)
                    );
                }, 3);
    
                $imported++;
            } catch (Throwable $e) {
                $skipped++;
    
                Log::warning('Teacher import row failed', [
                    'row' => $rowNumber,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    
        return $this->buildPartsSummary(
            $imported,
            $skipped,
            $updated,
            count($rows),
        );
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
