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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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

            $arm = $this->resolveClassArm($classLevelId, $data['class_arm'] ?? null, $classArms);

            if ($arm['ambiguous']) {
                $errors[] = [
                    'row' => $row['row'],
                    'errors' => [
                        'class_arm' => [
                            "Class arm '{$data['class_arm']}' is ambiguous for class level '{$data['class_level']}' "
                                .'(matches: '.implode(', ', $arm['candidates']).'). Use the full name.',
                        ],
                    ],
                ];

                continue;
            }

            if ($arm['id'] === null) {
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
            $row['_classArmId'] = $arm['id'];
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
    
        $duplicateRows = collect($duplicateByRow)
            ->pluck('row')
            ->map(static fn (mixed $row): int => (int) $row)
            ->unique()
            ->all();
    
        foreach ($rows as $index => $row) {
            $data = $row['data'];
    
            $rowNumber = (int) ($row['row'] ?? $index);
    
            $admissionNumber = strtoupper(
                (string) (
                    $data['admission_number']
                    ?? $this->student->generateAdmissionNumber()
                )
            );
    
            $email = $data['email']
                ?? $admissionNumber.'@student.edu';
    
            try {
                if (in_array($rowNumber, $duplicateRows, true)) {
                    if (! $this->overwriteExisting) {
                        $skipped++;
    
                        continue;
                    }
    
                    DB::connection('tenant')->transaction(
                        function () use (
                            $data,
                            $email,
                            $admissionNumber,
                            $row
                        ): void {
                            $existingUser = User::role(
                                RoleType::Student->value
                            )
                                ->where(function ($query) use (
                                    $email,
                                    $admissionNumber
                                ): void {
                                    $query
                                        ->where('email', $email)
                                        ->orWhereHas(
                                            'studentProfile',
                                            function ($profileQuery) use (
                                                $admissionNumber
                                            ): void {
                                                $profileQuery->where(
                                                    'admission_number',
                                                    $admissionNumber
                                                );
                                            }
                                        );
                                })
                                ->first();
    
                            if ($existingUser === null) {
                                throw new RuntimeException(
                                    "Existing student could not be found for row {$row['row']}."
                                );
                            }
    
                            $existingUser->update([
                                'first_name' => $data['first_name'],
                                'last_name' => $data['last_name'],
                                'phone' => $data['phone']
                                    ?? $existingUser->phone,
                            ]);
    
                            $profile = $existingUser->studentProfile;
    
                            if ($profile === null) {
                                throw new RuntimeException(
                                    "Student profile is missing for user {$existingUser->id}."
                                );
                            }
    
                            $profile->update([
                                'class_level_id' => $row['_classLevelId'],
                                'class_arm_id' => $row['_classArmId'],
                                'date_of_birth' => $data['date_of_birth']
                                    ?? $profile->date_of_birth,
                                'gender' => $data['gender']
                                    ?? $profile->gender,
                                'guardian_email' => $data['guardian_email']
                                    ?? $profile->guardian_email,
                            ]);
                        },
                        3
                    );
    
                    $updated++;
    
                    continue;
                }
    
                $payload = $this->buildPayload(
                    $data,
                    $row['_classLevelId'],
                    $row['_classArmId'],
                    $admissionNumber,
                    $email,
                );
    
                DB::connection('tenant')->transaction(
                    function () use ($payload): void {
                        $this->student->create($payload);
                    },
                    3
                );
    
                $imported++;
            } catch (Throwable $e) {
                $skipped++;
    
                Log::warning('Student import row failed', [
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

    private function resolveClassLevelId(?string $name, $classLevels): ?string
    {
        if ($name === null) {
            return null;
        }

        $level = $classLevels->get(NormalizeName::canonical($name));

        return $level ? (string) $level : null;
    }

    /**
     * @return array{id: ?string, ambiguous: bool, candidates: array<int, string>}
     */
    private function resolveClassArm(
        ?string $classLevelId,
        ?string $name,
        $classArms,
    ): array {
        if ($classLevelId === null || blank($name)) {
            return ['id' => null, 'ambiguous' => false, 'candidates' => []];
        }

        $inputSegments = $this->segments(NormalizeName::canonical($name));
        $arms = $classArms->get($classLevelId, collect());

        if ($inputSegments === []) {
            return ['id' => null, 'ambiguous' => false, 'candidates' => []];
        }

        $matches = $arms->filter(function ($arm) use ($inputSegments) {
            $armSegments = $this->segments($arm->normalized_name);

            return count($armSegments) >= count($inputSegments)
                && array_slice($armSegments, -count($inputSegments)) === $inputSegments;
        });

        if ($matches->count() !== 1) {
            $candidates = $matches
                ->sortBy('normalized_name')
                ->pluck('normalized_name')
                ->map(fn ($v) => str($v)->title())
                ->all();

            return [
                'id' => null,
                'ambiguous' => $matches->count() > 1,
                'candidates' => $candidates,
            ];
        }

        return ['id' => (string) $matches->first()->id, 'ambiguous' => false, 'candidates' => []];
    }

    private function segments(string $value): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($value))));
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
