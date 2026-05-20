<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Tenants\Student\StudentAction;
use App\Data\Results\ImportResult;
use App\Data\Schemas\StudentImportSchema;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\User;
use App\Support\CsvHeaderNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentImportService
{
    public function __construct(
        private readonly StudentAction $studentAction,
    ) {}

    public function import(array $validated, string $filePath, bool $dryRun = true): ImportResult
    {
        $overrideClassLevelId = $validated['class_level_id'] ?? null;
        $overwriteExisting = $validated['overwrite_existing'] ?? null;

        if (! is_readable($filePath)) {
            return new ImportResult(success: false, message: 'Could not read file.');
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return new ImportResult(success: false, message: 'Could not open file.');
        }

        try {
            // Stage 1 — Header Validation
            $headers = $this->readHeaders($handle);
            if ($headers === []) {
                return new ImportResult(success: false, message: 'CSV file is empty or missing headers.');
            }

            $missing = StudentImportSchema::missingRequiredHeaders($headers);
            if ($missing !== []) {
                return new ImportResult(
                    success: false,
                    message: 'CSV is missing required column(s): '.implode(', ', $missing),
                    missingHeaders: $missing,
                );
            }

            // Stage 2 — Row Validation & Duplicate Detection
            $classLevels = ClassLevel::query()->get()->keyBy(
                fn ($level) => strtolower(trim($level->name))
            );
            $classArms = ClassArm::query()->get()->keyBy(
                fn ($arm) => $arm->class_level_id.':'.strtolower(trim($arm->name))
            );

            $parsed = $this->parseAndValidateRows($handle, $headers, $classLevels, $classArms, $overrideClassLevelId);

            if ($parsed['errors'] !== []) {
                return new ImportResult(
                    success: false,
                    message: count($parsed['errors']).' row(s) have errors. Import cannot proceed.',
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
                    message: count($conflictErrors).' row(s) have data conflicts. Import cannot proceed.',
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
                    ? 'Preview complete. '.count($duplicates).' duplicate record(s) found.'
                    : 'Preview complete. '.count($parsed['rows']).' rows ready for import.';

                return new ImportResult(
                    success: true,
                    message: $msg,
                    totalRows: $parsed['totalRows'],
                    duplicates: $duplicates,
                    canProceed: true,
                );
            }

            // Stage 3 — Process
            $duplicateByRow = [];
            foreach ($duplicates as $d) {
                $duplicateByRow[$d['row']] = $d;
            }

            try {
                $importSummary = DB::transaction(function () use ($parsed, $duplicateByRow, $overwriteExisting) {
                    $imported = 0;
                    $skipped = 0;
                    $updated = 0;

                    $reservedNumbers = [];
                    foreach ($parsed['rows'] as $row) {
                        if ($row['admission_number']) {
                            $reservedNumbers[] = strtoupper(trim($row['admission_number']));
                        }
                    }

                    foreach ($parsed['rows'] as $row) {
                        $rn = $row['row_number'];

                        if (isset($duplicateByRow[$rn])) {
                            if ($overwriteExisting === 'update') {
                                $dup = $duplicateByRow[$rn];
                                $this->studentAction->update(
                                    $this->buildPayload($row['data'], $row['class_level_id'], $row['class_arm_id'], $dup['admission_number'], $dup['email']),
                                    $dup['user_id'],
                                );
                                $updated++;
                            } else {
                                $skipped++;
                            }

                            continue;
                        }

                        $admissionNumber = $row['admission_number'];
                        if (! $admissionNumber) {
                            $admissionNumber = $this->nextAvailableAdmissionNumber($reservedNumbers);
                            $reservedNumbers[] = $admissionNumber;
                        }
                        $admissionNumber = strtoupper(trim($admissionNumber));
                        $email = $row['email'] ?: "{$admissionNumber}@student.local";

                        $this->studentAction->create(
                            $this->buildPayload($row['data'], $row['class_level_id'], $row['class_arm_id'], $admissionNumber, $email)
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
                    message: 'Import failed: '.$e->getMessage(),
                    totalRows: $parsed['totalRows'],
                    canProceed: false,
                );
            }

            $totalProcessed = $importSummary['imported'] + $importSummary['updated'];

            return new ImportResult(
                success: true,
                message: implode(', ', $importSummary['parts']).'.',
                totalRows: $parsed['totalRows'],
                imported: $totalProcessed,
                skipped: $importSummary['skipped'],
                updated: $importSummary['updated'],
            );
        } finally {
            fclose($handle);
        }
    }

    private function parseAndValidateRows(
        $handle,
        array $headers,
        $classLevels,
        $classArms,
        ?string $overrideClassLevelId,
    ): array {
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

            $validator = Validator::make($data, StudentImportSchema::validatorRules($overrideClassLevelId));
            if ($validator->fails()) {
                $errors[] = ['row' => $rowNumber, 'errors' => $validator->errors()->toArray()];

                continue;
            }

            $classLevelId = $overrideClassLevelId
                ?? $this->resolveClassLevelId($data['class_level'] ?? null, $classLevels);

            if (! $overrideClassLevelId && ! $classLevelId) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => ['class_level' => ["Class level '{$data['class_level']}' not found."]],
                ];

                continue;
            }

            $classArmId = $this->resolveClassArmId(
                $classLevelId ?? $overrideClassLevelId,
                $data['class_arm'] ?? null,
                $classArms,
            );

            if (! $classArmId && ! empty($data['class_arm'])) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => ['class_arm' => ["Class arm '{$data['class_arm']}' not found for the specified class level."]],
                ];

                continue;
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'data' => $data,
                'class_level_id' => $classLevelId ?? $overrideClassLevelId,
                'class_arm_id' => $classArmId,
                'admission_number' => $data['admission_number'] ?: null,
                'email' => $data['email'] ?: null,
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
        $emails = array_values(array_unique(array_filter(array_column($rows, 'email'))));
        $admissionNumbers = array_values(array_unique(array_filter(array_column($rows, 'admission_number'))));

        if ($emails === [] && $admissionNumbers === []) {
            return [];
        }

        $existing = User::role('student')
            ->with('studentProfile')
            ->where(function ($q) use ($emails, $admissionNumbers) {
                if ($emails !== []) {
                    $q->whereIn('email', $emails);
                    if ($admissionNumbers !== []) {
                        $q->orWhereHas('studentProfile', fn ($p) => $p->whereIn('admission_number', $admissionNumbers));
                    }
                } elseif ($admissionNumbers !== []) {
                    $q->whereHas('studentProfile', fn ($p) => $p->whereIn('admission_number', $admissionNumbers));
                }
            })
            ->get();

        $byEmail = $existing->keyBy(fn ($u) => $u->email);

        $byAdmission = [];
        foreach ($existing as $user) {
            $num = $user->studentProfile?->admission_number;
            if ($num) {
                $byAdmission[$num] = $user;
            }
        }

        $duplicates = [];

        foreach ($rows as $row) {
            $rn = $row['row_number'];
            $email = $row['email'];
            $admissionNumber = $row['admission_number'];

            $byEmailMatch = $email && isset($byEmail[$email]);
            if ($byEmailMatch) {
                $found = $byEmail[$email];
                $duplicates[] = [
                    'row' => $rn,
                    'email' => $found->email,
                    'admission_number' => $found->studentProfile?->admission_number,
                    'user_id' => $found->id,
                ];

                continue;
            }

            $byAdmissionMatch = $admissionNumber && isset($byAdmission[$admissionNumber]);
            if ($byAdmissionMatch) {
                $found = $byAdmission[$admissionNumber];

                if ($email && $email !== $found->email) {
                    $errors[] = [
                        'row' => $rn,
                        'message' => "Admission number '{$admissionNumber}' already assigned to another student ({$found->email}).",
                    ];

                    continue;
                }

                $duplicates[] = [
                    'row' => $rn,
                    'email' => $found->email,
                    'admission_number' => $found->studentProfile?->admission_number,
                    'user_id' => $found->id,
                ];
            }
        }

        return $duplicates;
    }

    private function nextAvailableAdmissionNumber(array $reservedNumbers): string
    {
        $year = date('Y');
        $existing = StudentProfile::where('admission_number', 'like', "STU/{$year}/%")
            ->pluck('admission_number');

        $occupied = array_merge($existing->toArray(), $reservedNumbers);
        $occupied = array_unique($occupied);

        $candidate = 1;
        $number = "STU/{$year}/".str_pad((string) $candidate, 4, '0', STR_PAD_LEFT);

        while (in_array(strtoupper($number), $occupied, true)) {
            $candidate++;
            $number = "STU/{$year}/".str_pad((string) $candidate, 4, '0', STR_PAD_LEFT);
        }

        return $number;
    }

    private function readHeaders($handle): array
    {
        $headers = fgetcsv($handle);
        if ($headers === false || $headers === null) {
            return [];
        }

        return CsvHeaderNormalizer::normalizeHeaders($headers);
    }

    private function normalizeRow(array $data): array
    {
        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
    }

    private function resolveClassLevelId(?string $name, $classLevels): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        return $classLevels->get(strtolower(trim($name)))?->id;
    }

    private function resolveClassArmId(string $classLevelId, ?string $name, $classArms): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        return $classArms->get($classLevelId.':'.strtolower(trim($name)))?->id;
    }

    private function buildPayload(array $data, ?string $classLevelId, ?string $classArmId, string $admissionNumber, string $email): array
    {
        return [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'admission_number' => $admissionNumber,
            'class_level_id' => $classLevelId,
            'class_arm_id' => $classArmId,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'guardian_email' => $data['guardian_email'] ?? null,
        ];
    }
}
