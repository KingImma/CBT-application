<?php

declare(strict_types=1);

namespace App\Domains\Import\Actions;

use App\Domains\Import\Data\ImportResult;
use App\Domains\Import\Data\Schemas\TeacherImportSchema;
use App\Domains\Import\Support\BulkImportWriter;
use App\Domains\Teachers\Actions\TeacherService;
use App\Enums\RoleType;
use App\Models\Tenant\TeacherProfile;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ImportTeachers extends CsvImport
{
    private readonly BulkImportWriter $writer;

    public function __construct(private TeacherService $teacher)
    {
        $this->writer = new BulkImportWriter();
    }

    protected function schemaClass(): string
    {
        return TeacherImportSchema::class;
    }

    /**
     * Detect existing teachers with two queries for the whole file.
     *
     * A row counts as a duplicate when any identity column (email or staff id)
     * already belongs to an existing teacher.
     */
    protected function findDuplicates(array $rows, array &$errors): array
    {
        if ($rows === []) {
            return [];
        }

        $emails = [];
        $staffIds = [];

        foreach ($rows as $row) {
            $email = $this->normalizeEmail($row['data']['email'] ?? null);
            if ($email !== null) {
                $emails[$email] = true;
            }

            $staffId = $this->normalizeStaffId($row['data']['staff_id'] ?? null);
            if ($staffId !== null) {
                $staffIds[$staffId] = true;
            }
        }

        $existingByEmail = [];
        $existingByStaffId = [];

        if ($emails !== [] || $staffIds !== []) {
            $existing = User::query()
                ->role(RoleType::Teacher->value)
                ->select(['users.id', 'users.email'])
                ->with(['teacherProfile' => fn ($query) => $query->select(['id', 'user_id', 'staff_id'])])
                ->where(function ($query) use ($emails, $staffIds): void {
                    if ($emails !== []) {
                        $query->whereIn('users.email', array_keys($emails));
                    }

                    if ($staffIds !== []) {
                        $query->orWhereHas(
                            'teacherProfile',
                            fn ($profile) => $profile->whereIn('staff_id', array_keys($staffIds)),
                        );
                    }
                })
                ->get();

            foreach ($existing as $user) {
                if ($user->email !== null) {
                    $existingByEmail[$user->email] = (string) $user->id;
                }

                $staffId = $user->teacherProfile?->getAttribute('staff_id');

                if ($staffId !== null && trim((string) $staffId) !== '') {
                    $existingByStaffId[strtoupper(trim((string) $staffId))] = (string) $user->id;
                }
            }
        }

        $resolvedRows = [];

        foreach ($rows as $rowNumber => $row) {
            $email = $this->normalizeEmail($row['data']['email'] ?? null);
            $staffId = $this->normalizeStaffId($row['data']['staff_id'] ?? null);

            $existingId = null;
            $duplicates = [];

            if ($email !== null && isset($existingByEmail[$email])) {
                $existingId = $existingByEmail[$email];
                $duplicates[] = ['key' => 'email', 'value' => $email];
            }

            if ($staffId !== null && isset($existingByStaffId[$staffId])) {
                $existingId ??= $existingByStaffId[$staffId];
                $duplicates[] = ['key' => 'staff_id', 'value' => $staffId];
            }

            if ($duplicates !== []) {
                $row['_duplicates'] = $duplicates;
                $row['_existingId'] = $existingId;
            }

            $resolvedRows[$rowNumber] = $row;
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

            Log::warning('Teacher import batch write failed, retrying row by row', [
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
        $passwordHash = Hash::make($this->teacher->defaultPassword());

        $reserved = [];
        foreach ($toCreate as $row) {
            $staffId = $this->normalizeStaffId($row['data']['staff_id'] ?? null);
            if ($staffId !== null) {
                $reserved[$staffId] = true;
            }
        }

        $allocateStaffId = $this->writer->sequentialNumberAllocator(
            'TCH/'.date('Y').'/',
            'teacher_profiles',
            'staff_id',
            3,
            $reserved,
        );

        $users = [];
        $profiles = [];
        $userIds = [];
        $emails = [];

        $seenEmails = [];
        $seenStaffIds = [];
        $skipped = 0;
        $created = 0;

        foreach ($toCreate as $index => $row) {
            if ($index > 0 && $index % 250 === 0) {
                $this->progress()?->advance($index, $created, $skipped, 0);
            }

            $data = $row['data'];

            $email = $this->normalizeEmail($data['email'] ?? null);

            if ($email === null) {
                $skipped++;

                Log::warning('Teacher import row skipped: missing email address', [
                    'row' => $row['row'] ?? null,
                ]);

                continue;
            }

            $staffId = $this->normalizeStaffId($data['staff_id'] ?? null) ?? $allocateStaffId();

            // The same person listed twice in one file: keep the first row.
            if (isset($seenEmails[$email]) || isset($seenStaffIds[$staffId])) {
                $skipped++;

                Log::warning('Teacher import row skipped: duplicate inside the uploaded file', [
                    'row' => $row['row'] ?? null,
                    'email' => $email,
                    'staff_id' => $staffId,
                ]);

                continue;
            }

            $seenEmails[$email] = true;
            $seenStaffIds[$staffId] = true;

            $userId = (string) Str::orderedUuid();

            $users[] = [
                'id' => $userId,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'password' => $passwordHash,
                'phone' => $this->blankToNull($data['phone'] ?? null),
                'role' => RoleType::Teacher->value,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $profiles[] = [
                'id' => (string) Str::orderedUuid(),
                'user_id' => $userId,
                'staff_id' => $staffId,
                'qualification' => $this->blankToNull($data['qualification'] ?? null),
                'gender' => null,
                'class_level_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $userIds[] = $userId;
            $emails[] = $email;
            $created++;
        }

        if ($users !== []) {
            $this->writer->insert('users', $users);
            $this->writer->assignRole(RoleType::Teacher, $userIds);
            $this->writer->insert('teacher_profiles', $profiles);

            $this->progress()?->advance($created + $skipped, $created, $skipped, 0);
        }

        $updated = $this->applyUpdates($toUpdate);

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
     */
    private function applyUpdates(array $toUpdate): int
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

        $existingProfiles = TeacherProfile::query()
            ->whereIn('user_id', $ids)
            ->select(['id', 'user_id', 'staff_id', 'qualification'])
            ->get()
            ->keyBy(fn (TeacherProfile $profile): string => (string) $profile->user_id);

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
                'staff_id' => $this->normalizeStaffId($data['staff_id'] ?? null) ?? $profile?->staff_id,
                'qualification' => $this->blankToNull($data['qualification'] ?? null) ?? $profile?->qualification,
                'updated_at' => $now,
            ];
        }

        $this->writer->upsert(
            'users',
            $userRows,
            ['id'],
            ['first_name', 'last_name', 'phone', 'updated_at'],
        );

        $this->writer->upsert(
            'teacher_profiles',
            $profileRows,
            ['user_id'],
            ['staff_id', 'qualification', 'updated_at'],
        );

        return count($userRows);
    }

    /**
     * An email that already belongs to a student/admin would abort the whole
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

                Log::warning('Teacher import row skipped: email belongs to a non-teacher account', [
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
            $this->writer->syncTenantUserIndex(RoleType::Teacher, $emails);
        } catch (Throwable $e) {
            Log::error('Teacher import committed but the central login index update failed', [
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
            $existingId = $row['_existingId'] ?? null;

            try {
                $outcome = DB::connection('tenant')->transaction(
                    fn (): array => $this->writeBatch(
                        $existingId !== null && $this->overwriteExisting ? [] : [$row],
                        $existingId !== null && $this->overwriteExisting ? [(string) $existingId => $row] : [],
                    ),
                    3,
                );

                $imported += $outcome['created'];
                $updated += $outcome['updated'];
                $emails = array_merge($emails, $outcome['emails']);
            } catch (Throwable $e) {
                $skipped++;
                $firstError ??= $e->getMessage();

                Log::warning('Teacher import row failed', [
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

    private function normalizeStaffId(mixed $value): ?string
    {
        $staffId = strtoupper(trim((string) ($value ?? '')));

        return $staffId === '' ? null : $staffId;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
