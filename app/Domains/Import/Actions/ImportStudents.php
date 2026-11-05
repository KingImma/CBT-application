<?php

declare(strict_types=1);

namespace App\Domains\Import\Actions;

use App\Domains\Import\Data\ImportResult;
use App\Domains\Import\Data\Schemas\StudentImportSchema;
use App\Domains\Import\Support\BulkImportWriter;
use App\Domains\Students\Actions\StudentService;
use App\Enums\ExamStatus;
use App\Enums\RoleType;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\User;
use App\Shared\Support\NormalizeName;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ImportStudents extends CsvImport
{
    private readonly BulkImportWriter $writer;

    public function __construct(private StudentService $student)
    {
        $this->writer = new BulkImportWriter();
    }

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

    /**
     * Detect existing students with two queries for the whole file.
     *
     * A row counts as a duplicate when any identity column (email or admission
     * number) already belongs to an existing student.
     */
    protected function findDuplicates(array $rows, array &$errors): array
    {
        if ($rows === []) {
            return [];
        }

        $emails = [];
        $admissionNumbers = [];

        foreach ($rows as $row) {
            $email = $this->normalizeEmail($row['data']['email'] ?? null);
            if ($email !== null) {
                $emails[$email] = true;
            }

            $admissionNumber = $this->normalizeAdmissionNumber($row['data']['admission_number'] ?? null);
            if ($admissionNumber !== null) {
                $admissionNumbers[$admissionNumber] = true;
            }
        }

        $existingByEmail = [];
        $existingByAdmissionNumber = [];

        if ($emails !== [] || $admissionNumbers !== []) {
            $existing = User::query()
                ->role(RoleType::Student->value)
                ->select(['users.id', 'users.email'])
                ->with(['studentProfile' => fn ($query) => $query->select(['id', 'user_id', 'admission_number'])])
                ->where(function ($query) use ($emails, $admissionNumbers): void {
                    if ($emails !== []) {
                        $query->whereIn('users.email', array_keys($emails));
                    }

                    if ($admissionNumbers !== []) {
                        $query->orWhereHas(
                            'studentProfile',
                            fn ($profile) => $profile->whereIn('admission_number', array_keys($admissionNumbers)),
                        );
                    }
                })
                ->get();

            foreach ($existing as $user) {
                if ($user->email !== null) {
                    $existingByEmail[$user->email] = (string) $user->id;
                }

                $admissionNumber = $user->studentProfile?->getAttribute('admission_number');

                if ($admissionNumber !== null && trim((string) $admissionNumber) !== '') {
                    $existingByAdmissionNumber[strtoupper(trim((string) $admissionNumber))] = (string) $user->id;
                }
            }
        }

        $resolvedRows = [];

        foreach ($rows as $row) {
            $email = $this->normalizeEmail($row['data']['email'] ?? null);
            $admissionNumber = $this->normalizeAdmissionNumber($row['data']['admission_number'] ?? null);

            $existingId = null;
            $duplicates = [];

            if ($email !== null && isset($existingByEmail[$email])) {
                $existingId = $existingByEmail[$email];
                $duplicates[] = ['key' => 'email', 'value' => $email];
            }

            if ($admissionNumber !== null && isset($existingByAdmissionNumber[$admissionNumber])) {
                $existingId ??= $existingByAdmissionNumber[$admissionNumber];
                $duplicates[] = ['key' => 'admission_number', 'value' => $admissionNumber];
            }

            if ($duplicates !== []) {
                $row['_duplicates'] = $duplicates;
                $row['_existingId'] = $existingId;
            }

            $resolvedRows[] = $row;
        }

        return $resolvedRows;
    }

    /**
     * Write the file in batched statements: a constant number of queries
     * regardless of how many rows the file contains.
     */
    protected function processRows(
        array $rows,
        array $duplicateByRow,
    ): ImportResult {
        $toCreate = [];
        $toUpdate = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $existingId = $row['_existingId'] ?? null;

            if ($existingId !== null) {
                if (! $this->overwriteExisting) {
                    $skipped++;

                    continue;
                }

                $toUpdate[(string) $existingId] = $row;

                continue;
            }

            $toCreate[] = $row;
        }

        $toCreate = $this->dropEmailsOwnedByOtherRoles($toCreate, $skipped);

        $createdEmails = [];
        $created = 0;
        $updated = 0;

        try {
            [$created, $updated, $skippedFromFile, $createdEmails] = DB::connection('tenant')->transaction(
                function () use ($toCreate, $toUpdate): array {
                    $outcome = $this->writeBatch($toCreate, $toUpdate);

                    return [
                        $outcome['created'],
                        $outcome['updated'],
                        $outcome['skipped'],
                        $outcome['emails'],
                    ];
                },
                3,
            );
        } catch (Throwable $e) {
            if (! config('imports.fallback_row_by_row', true)) {
                throw $e;
            }

            Log::warning('Student import batch write failed, retrying row by row', [
                'rows' => count($toCreate) + count($toUpdate),
                'error' => $e->getMessage(),
            ]);

            return $this->processRowsRowByRow($rows, $duplicateByRow);
        }

        $skipped += $skippedFromFile;

        $this->syncLoginIndex($createdEmails);

        return $this->buildPartsSummary(
            $created,
            $skipped,
            $updated,
            count($rows),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $toCreate
     * @param  array<string, array<string, mixed>>  $toUpdate
     * @return array{created: int, updated: int, skipped: int, emails: list<string>}
     */
    private function writeBatch(array $toCreate, array $toUpdate): array
    {
        $now = now();
        $passwordHash = Hash::make($this->student->defaultPassword());

        $reserved = [];
        foreach ($toCreate as $row) {
            $admissionNumber = $this->normalizeAdmissionNumber($row['data']['admission_number'] ?? null);
            if ($admissionNumber !== null) {
                $reserved[$admissionNumber] = true;
            }
        }

        $allocateAdmissionNumber = $this->writer->sequentialNumberAllocator(
            'STU/'.date('Y').'/',
            'student_profiles',
            'admission_number',
            4,
            $reserved,
        );

        $users = [];
        $profiles = [];
        $userIds = [];
        $emails = [];
        $touched = [];

        $seenEmails = [];
        $seenAdmissionNumbers = [];
        $skipped = 0;
        $created = 0;

        foreach ($toCreate as $index => $row) {
            if ($index > 0 && $index % 250 === 0) {
                $this->progress()?->advance($index, $created, $skipped, 0);
            }

            $data = $row['data'];

            $admissionNumber = $this->normalizeAdmissionNumber($data['admission_number'] ?? null)
                ?? $allocateAdmissionNumber();

            $email = $this->normalizeEmail($data['email'] ?? null) ?? $admissionNumber.'@student.edu';

            // The same person listed twice in one file: keep the first row.
            if (isset($seenEmails[$email]) || isset($seenAdmissionNumbers[$admissionNumber])) {
                $skipped++;

                Log::warning('Student import row skipped: duplicate inside the uploaded file', [
                    'row' => $row['row'] ?? null,
                    'email' => $email,
                    'admission_number' => $admissionNumber,
                ]);

                continue;
            }

            $seenEmails[$email] = true;
            $seenAdmissionNumbers[$admissionNumber] = true;

            $userId = (string) Str::orderedUuid();

            $users[] = [
                'id' => $userId,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'password' => $passwordHash,
                'phone' => $this->blankToNull($data['phone'] ?? null),
                'role' => RoleType::Student->value,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $profiles[] = [
                'id' => (string) Str::orderedUuid(),
                'user_id' => $userId,
                'class_level_id' => $row['_classLevelId'],
                'class_arm_id' => $row['_classArmId'],
                'admission_number' => $admissionNumber,
                'date_of_birth' => $this->toDate($data['date_of_birth'] ?? null),
                'gender' => $this->blankToNull($data['gender'] ?? null),
                'guardian_email' => $this->blankToNull($data['guardian_email'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $userIds[] = $userId;
            $emails[] = $email;
            $touched[$row['_classLevelId'].'|'.$row['_classArmId']] = [
                (string) $row['_classLevelId'],
                (string) $row['_classArmId'],
            ];
            $created++;
        }

        if ($users !== []) {
            $this->writer->insert('users', $users);
            $this->writer->assignRole(RoleType::Student, $userIds);
            $this->writer->insert('student_profiles', $profiles);

            $this->progress()?->advance($created + $skipped, $created, $skipped, 0);
        }

        $updated = $this->applyUpdates($toUpdate, $touched);

        if (config('imports.recalculate_exam_attendance', true)) {
            $this->recalculateExamAttendance(array_values($touched));
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'emails' => $emails,
        ];
    }

    /**
     * Bulk update of the overwrite path (overwrite_existing=update).
     *
     * @param  array<string, array<string, mixed>>  $toUpdate
     * @param  array<string, array{0: string, 1: ?string}>  $touched
     */
    private function applyUpdates(array $toUpdate, array &$touched): int
    {
        if ($toUpdate === []) {
            return 0;
        }

        $ids = array_keys($toUpdate);

        $existingUsers = User::query()
            ->whereIn('id', $ids)
            ->select(['id', 'email', 'password', 'role', 'is_active', 'phone'])
            ->get()
            ->keyBy(fn (User $user): string => (string) $user->id);

        $existingProfiles = StudentProfile::query()
            ->whereIn('user_id', $ids)
            ->select(['id', 'user_id', 'admission_number', 'date_of_birth', 'gender', 'guardian_email'])
            ->get()
            ->keyBy(fn (StudentProfile $profile): string => (string) $profile->user_id);

        $now = now();
        $userRows = [];
        $profileRows = [];

        foreach ($toUpdate as $id => $row) {
            $user = $existingUsers->get($id);

            if ($user === null) {
                continue;
            }

            $data = $row['data'];
            $profile = $existingProfiles->get($id);

            $userRows[] = [
                'id' => $id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $this->blankToNull($data['phone'] ?? null) ?? $user->phone,
                // NOT NULL columns are carried over unchanged: the database
                // validates them on the INSERT branch before the conflict is
                // resolved, so an upsert with a partial row would be rejected.
                'email' => $user->email,
                'password' => $user->getAttribute('password'),
                'role' => $user->getAttribute('role'),
                'is_active' => $user->is_active,
                'updated_at' => $now,
            ];

            $profileRows[] = [
                'id' => $profile?->getKey() ?? (string) Str::orderedUuid(),
                'user_id' => $id,
                'class_level_id' => $row['_classLevelId'],
                'class_arm_id' => $row['_classArmId'],
                'admission_number' => $this->normalizeAdmissionNumber($data['admission_number'] ?? null)
                    ?? $profile?->admission_number,
                'date_of_birth' => $this->toDate($data['date_of_birth'] ?? null)
                    ?? $profile?->date_of_birth?->toDateString(),
                'gender' => $this->blankToNull($data['gender'] ?? null) ?? $profile?->gender,
                'guardian_email' => $this->blankToNull($data['guardian_email'] ?? null) ?? $profile?->guardian_email,
                'updated_at' => $now,
            ];

            $touched[$row['_classLevelId'].'|'.$row['_classArmId']] = [
                (string) $row['_classLevelId'],
                (string) $row['_classArmId'],
            ];
        }

        $this->writer->upsert(
            'users',
            $userRows,
            ['id'],
            ['first_name', 'last_name', 'phone', 'updated_at'],
        );

        $this->writer->upsert(
            'student_profiles',
            $profileRows,
            ['user_id'],
            ['class_level_id', 'class_arm_id', 'admission_number', 'date_of_birth', 'gender', 'guardian_email', 'updated_at'],
        );

        return count($userRows);
    }

    /**
     * Recompute expected_attempts once per affected class arm instead of once
     * per imported student.
     *
     * @param  list<array{0: string, 1: ?string}>  $pairs
     */
    private function recalculateExamAttendance(array $pairs): void
    {
        foreach ($pairs as [$classLevelId, $classArmId]) {
            Exam::query()
                ->where('status', ExamStatus::Active->value)
                ->where('class_level_id', $classLevelId)
                ->where(fn ($query) => $query->whereNull('class_arm_id')->orWhere('class_arm_id', $classArmId))
                ->get()
                ->each(fn (Exam $exam) => $exam->update(['expected_attempts' => $exam->expectedAttempts()]));
        }
    }

    /**
     * An email that already belongs to a teacher/admin would abort the whole
     * insert statement, so those rows are dropped up-front instead.
     *
     * @param  list<array<string, mixed>>  $toCreate
     */
    private function dropEmailsOwnedByOtherRoles(array $toCreate, int &$skipped): array
    {
        $emails = [];

        foreach ($toCreate as $row) {
            $email = $this->normalizeEmail($row['data']['email'] ?? null);
            if ($email !== null) {
                $emails[] = $email;
            }
        }

        if ($emails === []) {
            return $toCreate;
        }

        $taken = User::query()
            ->whereIn('email', array_values(array_unique($emails)))
            ->pluck('email')
            ->flip();

        if ($taken->isEmpty()) {
            return $toCreate;
        }

        $kept = [];

        foreach ($toCreate as $row) {
            $email = $this->normalizeEmail($row['data']['email'] ?? null);

            if ($email !== null && $taken->has($email)) {
                $skipped++;

                Log::warning('Student import row skipped: email belongs to a non-student account', [
                    'row' => $row['row'] ?? null,
                    'email' => $email,
                ]);

                continue;
            }

            $kept[] = $row;
        }

        return $kept;
    }

    private function syncLoginIndex(array $emails): void
    {
        if ($emails === []) {
            return;
        }

        try {
            $this->writer->syncTenantUserIndex(RoleType::Student, $emails);
        } catch (Throwable $e) {
            Log::error('Student import committed but the central login index update failed', [
                'rows' => count($emails),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Safety net used only when the batched write throws: retry one row per
     * transaction so a single bad row cannot discard the rest of the import.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function processRowsRowByRow(array $rows, array $duplicateByRow): ImportResult
    {
        $imported = 0;
        $skipped = 0;
        $updated = 0;
        $firstError = null;
        $emails = [];

        foreach ($rows as $row) {
            try {
                $outcome = DB::connection('tenant')->transaction(
                    fn (): array => $this->writeBatch(
                        isset($row['_existingId']) && $this->overwriteExisting ? [] : [$row],
                        isset($row['_existingId']) && $this->overwriteExisting ? [$row['_existingId'] => $row] : [],
                    ),
                    3,
                );

                $imported += $outcome['created'];
                $updated += $outcome['updated'];
                $emails = array_merge($emails, $outcome['emails']);
            } catch (Throwable $e) {
                $skipped++;
                $firstError ??= $e->getMessage();

                Log::warning('Student import row failed', [
                    'row' => $row['row'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->syncLoginIndex($emails);

        if ($imported === 0 && $updated === 0 && $firstError !== null) {
            return new ImportResult(
                success: false,
                message: 'Import failed: '.$firstError,
                errors: [],
                totalRows: count($rows),
                canProceed: false,
            );
        }

        return $this->buildPartsSummary(
            $imported,
            $skipped,
            $updated,
            count($rows),
        );
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $email = trim((string) ($value ?? ''));

        return $email === '' ? null : $email;
    }

    private function normalizeAdmissionNumber(mixed $value): ?string
    {
        $admissionNumber = strtoupper(trim((string) ($value ?? '')));

        return $admissionNumber === '' ? null : $admissionNumber;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    private function toDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
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
}
