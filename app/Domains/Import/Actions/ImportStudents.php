<?php

declare(strict_types=1);

namespace App\Domains\Import\Actions;

use App\Domains\Import\Data\ImportResult;
use App\Domains\Import\Data\Schemas\StudentImportSchema;
use App\Domains\Students\Actions\StudentService;
use App\Enums\RoleType;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;
use App\Shared\Support\NormalizeName;

class ImportStudents extends CsvImport
{
    public function __construct(private StudentService $student) {}

    protected function schemaClass(): string
    {
        return StudentImportSchema::class;
    }

    protected function resolveReferences(array $rows, array &$errors): array
    {
        $classLevels = ClassLevel::pluck('id', 'normalized_name');
        $classArms = ClassArm::select('id', 'normalized_name', 'class_level_id')
            ->get()
            ->groupBy('class_level_id');

        $resolvedRows = [];
        foreach ($rows as $row) {
            $data = $row['data'];
            $classLevelId = $this->resolveClassLevelId(
                $data['class_level'] ?? null,
                $classLevels,
            );
            $classArmId = $this->resolveClassArmId(
                $classLevelId,
                $data['class_arm'] ?? null,
                $classArms,
            );

            if ($classLevelId === null) {
                $errors[] = [
                    'row' => $row['row'],
                    'errors' => [
                        'class_level' => [
                            "Class level '{$data['class_level']}' not found.",
                        ],
                    ],
                ];

                continue;
            }

            if ($classArmId === null && ! blank($data['class_arm'])) {
                $errors[] = [
                    'row' => $row['row'],
                    'errors' => [
                        'class_arm' => [
                            "Class arm '{$data['class_arm']}' not found for class level '{$data['class_level']}'.",
                        ],
                    ],
                ];

                continue;
            }

            $row['_classLevelId'] = $classLevelId;
            $row['_classArmId'] = $classArmId;
            $resolvedRows[] = $row;
        }

        return $resolvedRows;
    }

    protected function findDuplicates(array $rows, array &$errors): array
    {
        $identityFields = StudentImportSchema::IDENTITY;

        $resolvedRows = [];
        foreach ($rows as $row) {
            $data = $row['data'];
            $identifiers = $this->extractIdentifiers($data, $identityFields);

            $existing = User::role(RoleType::Student->value);
            foreach ($identifiers as $key => $value) {
                if ($key === 'email') {
                    $existing->where('email', $value);
                } else {
                    $existing->whereHas(
                        'studentProfile',
                        fn ($q) => $q->where($key, $key === 'admission_number' ? strtoupper($value) : $value),
                    );
                }
            }

            if ($identifiers !== [] && $existing->exists()) {
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
            $admissionNumber = strtoupper(
                $data['admission_number'] ??
                $this->student->generateAdmissionNumber()
            );
            $email = $data['email'] ?? $admissionNumber.'@student.edu';

            if (in_array($rn, $duplicateKeys)) {
                if ($this->overwriteExisting) {
                    $existingUser = User::role(RoleType::Student->value)
                        ->where(function ($q) use ($email, $admissionNumber) {
                            $q->where('email', $email)
                                ->orWhereHas('studentProfile', fn ($q2) => $q2->where('admission_number', $admissionNumber));
                        })
                        ->first();

                    if ($existingUser) {
                        $existingUser->update([
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'phone' => $data['phone'] ?? $existingUser->phone,
                        ]);

                        if ($existingUser->studentProfile) {
                            $existingUser->studentProfile->update([
                                'class_level_id' => $row['_classLevelId'],
                                'class_arm_id' => $row['_classArmId'],
                                'date_of_birth' => $data['date_of_birth'] ?? $existingUser->studentProfile->date_of_birth,
                                'gender' => $data['gender'] ?? $existingUser->studentProfile->gender,
                                'guardian_email' => $data['guardian_email'] ?? $existingUser->studentProfile->guardian_email,
                            ]);
                        }

                        $updated++;
                    } else {
                        $skipped++;
                    }

                    continue;
                }

                $skipped++;

                continue;
            }

            $payload = $this->buildPayload(
                $data,
                $row['_classLevelId'],
                $row['_classArmId'],
                $admissionNumber,
                $email,
            );
            $this->student->create($payload);
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

    private function resolveClassLevelId(?string $name, $classLevels): ?string
    {
        if ($name === null) {
            return null;
        }

        $level = $classLevels->get(NormalizeName::canonical($name));

        return $level ? (string) $level : null;
    }

    private function resolveClassArmId(
        ?string $classLevelId,
        ?string $name,
        $classArms,
    ): ?string {
        if ($classLevelId === null || $name === null) {
            return null;
        }

        $canonical = NormalizeName::canonical($name);
        $arms = $classArms->get($classLevelId, collect());

        $arm = $arms->firstWhere('normalized_name', $canonical);

        return $arm ? (string) $arm->id : null;
    }

    private function buildPayload(
        array $data,
        ?string $classLevelId,
        ?string $classArmId,
        string $admissionNumber,
        string $email,
    ): array {
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
