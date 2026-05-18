<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Tenants\Teacher\TeacherAction;
use App\Data\Schemas\TeacherImportSchema;
use App\Models\Tenant\User;
use App\Data\Results\ImportResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TeacherImportService
{
    public function __construct(
        private readonly TeacherAction $teacherAction,
    ) {}

    public function import(array $validated, string $filePath, bool $dryRun = true): ImportResult
    {
        $overwriteExisting = $validated['overwrite_existing'] ?? null;

        if (! is_readable($filePath)) {
            return new ImportResult(success: false, message: 'Could not read file.');
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return new ImportResult(success: false, message: 'Could not open file.');
        }

        try {
            $headers = $this->readHeaders($handle);
            if ($headers === []) {
                return new ImportResult(success: false, message: 'CSV file is empty or missing headers.');
            }

            $missing = TeacherImportSchema::missingRequiredHeaders($headers);
            if ($missing !== []) {
                return new ImportResult(
                    success: false,
                    message: 'CSV is missing required column(s): ' . implode(', ', $missing),
                    missingHeaders: $missing,
                );
            }

            $parsed = $this->parseAndValidateRows($handle, $headers);

            if ($parsed['errors'] !== []) {
                return new ImportResult(
                    success: false,
                    message: count($parsed['errors']) . ' row(s) have errors. Import cannot proceed.',
                    totalRows: $parsed['totalRows'],
                    errors: $parsed['errors'],
                    canProceed: false,
                );
            }

            $conflictErrors = [];
            $duplicates = $this->findDuplicates($parsed['rows'], $conflictErrors);

            if ($conflictErrors !== []) {
                return new ImportResult(
                    success: false,
                    message: count($conflictErrors) . ' row(s) have data conflicts. Import cannot proceed.',
                    totalRows: $parsed['totalRows'],
                    errors: $conflictErrors,
                    canProceed: false,
                );
            }

            if (! $dryRun && $duplicates !== [] && $overwriteExisting === null) {
                return new ImportResult(
                    success: false,
                    message: 'overwrite_existing is required when duplicates exist.',
                    canProceed: false,
                );
            }

            if ($dryRun) {
                $msg = $duplicates !== []
                    ? 'Preview complete. ' . count($duplicates) . ' duplicate record(s) found.'
                    : 'Preview complete. ' . count($parsed['rows']) . ' rows ready for import.';

                return new ImportResult(
                    success: true,
                    message: $msg,
                    totalRows: $parsed['totalRows'],
                    duplicates: $duplicates,
                    canProceed: true,
                );
            }

            $duplicateByRow = [];
            foreach ($duplicates as $d) {
                $duplicateByRow[$d['row']] = $d;
            }

            try {
                $importSummary = DB::transaction(function () use ($parsed, $duplicateByRow, $overwriteExisting) {
                    $imported = 0;
                    $skipped = 0;
                    $updated = 0;

                    foreach ($parsed['rows'] as $row) {
                        $rn = $row['row_number'];

                        if (isset($duplicateByRow[$rn])) {
                            if ($overwriteExisting === 'update') {
                                $dup = $duplicateByRow[$rn];
                                $payload = $this->buildPayload($row['data'], $dup['email']);
                                if (empty($row['data']['staff_id'])) {
                                    unset($payload['staff_id']);
                                }
                                $this->teacherAction->update(
                                    $payload,
                                    $dup['user_id'],
                                );
                                $updated++;
                            } else {
                                $skipped++;
                            }
                            continue;
                        }

                        $email = $row['data']['email'];

                        $this->teacherAction->create(
                            $this->buildPayload($row['data'], $email)
                        );
                        $imported++;
                    }

                    $parts = [];
                    if ($imported > 0) {
                        $parts[] = "{$imported} imported";
                    }
                    if ($updated > 0) {
                        $parts[] = "{$updated} updated";
                    }
                    if ($skipped > 0) {
                        $parts[] = "{$skipped} skipped (existing records)";
                    }

                    return compact('imported', 'skipped', 'updated', 'parts');
                });
            } catch (\Throwable $e) {
                return new ImportResult(
                    success: false,
                    message: 'Import failed: ' . $e->getMessage(),
                    totalRows: $parsed['totalRows'],
                    canProceed: false,
                );
            }

            $totalProcessed = $importSummary['imported'] + $importSummary['updated'];

            return new ImportResult(
                success: true,
                message: implode(', ', $importSummary['parts']) . '.',
                totalRows: $parsed['totalRows'],
                imported: $totalProcessed,
                skipped: $importSummary['skipped'],
                updated: $importSummary['updated'],
            );
        } finally {
            fclose($handle);
        }
    }

    private function parseAndValidateRows($handle, array $headers): array
    {
        $rows = [];
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) !== count($headers)) {
                $errors[] = ['row' => $rowNumber, 'errors' => ['csv' => ['Column count does not match header count.']]];
                continue;
            }

            $data = array_combine($headers, $row);
            if (! is_array($data)) {
                $errors[] = ['row' => $rowNumber, 'errors' => ['csv' => ['Could not parse row.']]];
                continue;
            }

            $data = $this->normalizeRow($data);

            $validator = Validator::make($data, TeacherImportSchema::validatorRules());
            if ($validator->fails()) {
                $errors[] = ['row' => $rowNumber, 'errors' => $validator->errors()->toArray()];
                continue;
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'data' => $data,
            ];
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
            'totalRows' => count($rows) + count($errors),
        ];
    }

    private function findDuplicates(array $rows, array &$errors): array
    {
        $emails = array_values(array_unique(array_filter(array_column(array_column($rows, 'data'), 'email'))));
        $staffIds = array_values(array_unique(array_filter(array_column(array_column($rows, 'data'), 'staff_id'))));

        if ($emails === [] && $staffIds === []) {
            return [];
        }

        $existing = User::role('teacher')
            ->with('teacherProfile')
            ->where(function ($q) use ($emails, $staffIds) {
                if ($emails !== []) {
                    $q->whereIn('email', $emails);
                    if ($staffIds !== []) {
                        $q->orWhereHas('teacherProfile', fn ($p) => $p->whereIn('staff_id', $staffIds));
                    }
                } elseif ($staffIds !== []) {
                    $q->whereHas('teacherProfile', fn ($p) => $p->whereIn('staff_id', $staffIds));
                }
            })
            ->get();

        $byEmail = $existing->keyBy(fn ($u) => $u->email);

        $byStaffId = [];
        foreach ($existing as $user) {
            $sid = $user->teacherProfile?->staff_id;
            if ($sid) {
                $byStaffId[$sid] = $user;
            }
        }

        $duplicates = [];

        foreach ($rows as $row) {
            $rn = $row['row_number'];
            $email = $row['data']['email'];
            $staffId = $row['data']['staff_id'] ?: null;

            $byEmailMatch = $email && isset($byEmail[$email]);
            if ($byEmailMatch) {
                $found = $byEmail[$email];
                $duplicates[] = [
                    'row' => $rn,
                    'email' => $found->email,
                    'staff_id' => $found->teacherProfile?->staff_id,
                    'user_id' => $found->id,
                ];
                continue;
            }

            if ($staffId && isset($byStaffId[$staffId])) {
                $found = $byStaffId[$staffId];

                if ($email && $email !== $found->email) {
                    $errors[] = [
                        'row' => $rn,
                        'message' => "Staff ID '{$staffId}' already assigned to another teacher ({$found->email}).",
                    ];
                    continue;
                }

                $duplicates[] = [
                    'row' => $rn,
                    'email' => $found->email,
                    'staff_id' => $found->teacherProfile?->staff_id,
                    'user_id' => $found->id,
                ];
            }
        }

        return $duplicates;
    }

    private function readHeaders($handle): array
    {
        $headers = fgetcsv($handle);
        if ($headers === false || $headers === null) {
            return [];
        }

        return array_map(fn ($h) => strtolower(trim($h)), $headers);
    }

    private function normalizeRow(array $data): array
    {
        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
    }

    private function buildPayload(array $data, string $email): array
    {
        return [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'qualification' => $data['qualification'] ?? null,
            'staff_id' => $data['staff_id'] ?? null,
        ];
    }
}
