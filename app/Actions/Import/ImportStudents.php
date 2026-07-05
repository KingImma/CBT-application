<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Tenants\Student\Student;
use App\Data\Results\ImportResult;
use App\Data\Schemas\StudentImportSchema;
use App\Enums\RoleType;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;

class ImportStudents extends CsvImport
{
    public function __construct(private Student $student) {}

    protected function schemaClass(): string
    {
        return StudentImportSchema::class;
    }

    protected function resolveReferences(array $rows, array &$errors): array
    {
        $classLevels = ClassLevel::pluck('id', 'name');
        $classArms = ClassArm::select('id', 'name', 'class_level_id')
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
                        fn ($q) => $q->where($key, $value),
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
            $admissionNumber =
                $data['admission_number'] ??
                $this->student->generateAdmissionNumber();
            $email = $data['email'] ?? $admissionNumber.'@student.edu';

            if (in_array($rn, $duplicateKeys)) {
                if ($this->overwriteExisting) {
                    $existingUser = User::role(RoleType::Student->value)
                        ->where('email', $email)
                        ->orWhereHas('studentProfile', fn ($q) => $q->where('admission_number', $admissionNumber))
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

        $level = $classLevels->get($name);

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

        $arms = $classArms->get($classLevelId, collect());

        $arm = $arms->firstWhere('name', $name);

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
