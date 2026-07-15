<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Tenancy\Actions\RemoveTenantUserIndex;
use App\Domains\Tenancy\Actions\SyncTenantUser;
use App\Domains\Auth\Actions\ResetUserPassword;
use App\Domains\Import\Actions\ImportTeachers;
use App\Domains\Import\Data\ImportResult;
use App\Domains\Import\Data\Schemas\TeacherImportSchema;
use App\Domains\Import\Jobs\ImportTeachersJob;
use App\Domains\Teachers\Data\TeacherData;
use App\Domains\Teachers\Actions\TeacherService;
use App\Domains\Tenancy\Events\UserActivated;
use App\Domains\Tenancy\Events\UserDeactivated;
use App\Enums\RoleType;
use App\Events\ActivityFeedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTeacherRequest;
use App\Http\Requests\Tenant\UpdateTeacherRequest;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\User;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Staff Directory
 * * APIs for managing teachers, roles, and subject assignments.
 */
class TeacherController extends Controller
{
    /**
     * List teachers with optional filters.
     *
     * @subgroup Teacher Management
     *
     * @queryParam status string Filter by status: active, inactive, all (default: active). No-example
     * @queryParam search string Search by name or email. No-example
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');
        $search = $request->query('search');

        $teachers = User::role(RoleType::Teacher->value)
            ->withTrashed()
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'is_active')
            ->with([
                'teacherProfile',
                'teacherAssignments.subject',
                'teacherAssignments.classLevel',
                'assignedClasses.classLevel',
                'assignedClasses.subjects',
            ])
            ->when($search !== null && trim($search) !== '', function ($query) use ($search) {
                $search = trim($search);

                return $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderBy('last_name')
            ->paginate(20);

        return ApiResponse::paginated(
            $teachers,
            'Teachers retrieved successfully.',
            TeacherData::collect($teachers->getCollection())
        );
    }

    /**
     * Create a new teacher.
     *
     * @subgroup Teacher Management
     *
     * @bodyParam first_name string required Teacher's first name. Example: "Jane"
     * @bodyParam last_name string required Teacher's last name. Example: "Smith"
     * @bodyParam email string required Teacher's email. No-example
     * @bodyParam phone string nullable Phone number. No-example
     * @bodyParam qualification string nullable Academic qualification. No-example
     * @bodyParam staff_id string nullable Unique staff ID. No-example
     * @bodyParam class_level_id string nullable Assigned class level UUID. No-example
     */
    public function store(StoreTeacherRequest $request, TeacherService $teacherService): JsonResponse
    {
        $result = $teacherService->create($request->validated());

        broadcast(new ActivityFeedEvent(
            channelType: 'school_admin',
            channelId: tenant('id'),
            action: 'teacher.created',
            description: "Teacher {$result['user']->first_name} {$result['user']->last_name} added.",
        ))->toOthers();

        return ApiResponse::created([
            'teacher' => TeacherData::from($result['user']->load('teacherProfile')),
            'temporary_password' => $result['password'],
        ], 'Teacher created.');
    }

    /**
     * Get a single teacher with their profile and assignments.
     *
     * @subgroup Teacher Management
     *
     * @urlParam id string required The teacher UUID.
     */
    public function show(string $id): JsonResponse
    {
        // Now finding by USER ID, not Profile ID
        $teacher = User::role(RoleType::Teacher->value)
            ->with([
                'teacherProfile',
                'assignedClasses.classLevel',
                'assignedClasses.subjects',
                'assignedClasses.assignedTeacher',
                'teacherAssignments.subject',
                'teacherAssignments.classLevel',
            ])->findOrFail($id);

        return ApiResponse::success(TeacherData::from($teacher), 'Teacher retrieved successfully.');
    }

    /**
     * Get the classes assigned to a teacher.
     *
     * @subgroup Teacher Classes & Subjects
     *
     * @urlParam id string required The teacher UUID.
     */
    public function classes(string $id): JsonResponse
    {
        $teacher = User::role(RoleType::Teacher->value)->findOrFail($id);

        $classes = ClassArm::where('assigned_teacher_id', $teacher->id)
            ->with(['classLevel', 'subjects'])
            ->get();

        return ApiResponse::success($classes, 'Teacher classes retrieved.');
    }

    /**
     * Get the subjects assigned to a teacher.
     *
     * @subgroup Teacher Classes & Subjects
     *
     * @urlParam id string required The teacher UUID.
     */
    public function subjects(string $id): JsonResponse
    {
        $teacher = User::role(RoleType::Teacher->value)->findOrFail($id);

        $subjectTeacherSubjects = $teacher->teacherAssignments()
            ->with('subject', 'classLevel')
            ->get()
            ->map(fn ($assignment) => [
                'subject' => $assignment->subject,
                'class_level' => $assignment->classLevel,
                'role' => 'subject_teacher',
            ]);

        $classArms = ClassArm::where('assigned_teacher_id', $teacher->id)
            ->with('classLevel', 'subjects')
            ->get();

        $classTeacherSubjects = $classArms->flatMap(fn ($arm) => $arm->subjects
            ->map(fn ($subject) => [
                'subject' => $subject,
                'class_level' => $arm->classLevel,
                'class_arm' => $arm,
                'role' => 'class_teacher',
            ])
        );

        $combinedSubjectAssignments = $subjectTeacherSubjects->concat(
            $classTeacherSubjects->reject(fn ($classTeacherSubject) => $subjectTeacherSubjects->contains(
                fn ($subjectTeacherSubject) => $subjectTeacherSubject['subject']->id === $classTeacherSubject['subject']->id
                    && $subjectTeacherSubject['class_level']->id === $classTeacherSubject['class_level']->id
            )
            )
        )->values();

        return ApiResponse::success($combinedSubjectAssignments, 'Teacher subjects retrieved.');
    }

    /**
     * Update a teacher's information.
     *
     * @subgroup Teacher Management
     *
     * @urlParam id string required The teacher UUID.
     *
     * @bodyParam first_name string First name. No-example
     * @bodyParam last_name string Last name. No-example
     * @bodyParam email string Email. No-example
     * @bodyParam phone string nullable Phone. No-example
     * @bodyParam qualification string nullable Qualification. No-example
     * @bodyParam staff_id string nullable Staff ID. No-example
     * @bodyParam class_level_id string nullable Class level UUID. No-example
     */
    public function update(UpdateTeacherRequest $request, TeacherService $teacherService, string $id): JsonResponse
    {
        $teacher = $teacherService->update($request->validated(), $id);

        return ApiResponse::success(TeacherData::from($teacher), 'Teacher updated successfully.');
    }

    /**
     * Revoke a teacher's access and soft-delete their account.
     *
     * @subgroup Teacher Status
     *
     * @urlParam id string required The teacher UUID.
     */
    public function revoke(RemoveTenantUserIndex $removeTenantUserIndex, string $id): JsonResponse
    {
        $teacher = User::role(RoleType::Teacher->value)->findOrFail($id);
        $this->authorize('revokeTeacher', $teacher);

        DB::transaction(function () use ($teacher, $removeTenantUserIndex) {
            $teacher->deactivate()->save();
            $teacher->tokens()->delete();

            // Fire the event manually here
            UserDeactivated::dispatch($teacher);

            TeacherSubjectAssignment::where('user_id', $teacher->id)->delete();
            $removeTenantUserIndex->execute($teacher->email);
            $teacher->delete();
        });

        return ApiResponse::message('Teacher revoked.');
    }

    /**
     * Permanently delete a teacher record.
     *
     * @subgroup Teacher Status
     *
     * @urlParam id string required The teacher UUID.
     */
    public function destroy(RemoveTenantUserIndex $removeTenantUserIndex, string $id): JsonResponse
    {
        $teacher = User::withTrashed()->role(RoleType::Teacher->value)->findOrFail($id);
        $this->authorize('deleteTeacher', $teacher);

        DB::transaction(function () use ($teacher, $removeTenantUserIndex) {
            $teacher->teacherProfile()->delete();
            TeacherSubjectAssignment::where('user_id', $teacher->id)->delete();
            $removeTenantUserIndex->execute($teacher->email);
            $teacher->syncRoles([]);
            $teacher->forceDelete();
        });

        return ApiResponse::message('Teacher permanently deleted.');
    }

    /**
     * Restore a previously revoked teacher.
     *
     * @subgroup Teacher Status
     *
     * @urlParam id string required The teacher UUID.
     */
    public function restore(SyncTenantUser $syncTenantUser, string $id): JsonResponse
    {
        $teacher = User::withTrashed()->role(RoleType::Teacher->value)->findOrFail($id);
        $this->authorize('restoreTeacher', $teacher);

        if (! $teacher->trashed()) {
            return ApiResponse::error('This teacher is already active and has not been deleted.', 422);
        }

        DB::transaction(function () use ($teacher, $syncTenantUser) {
            $teacher->restore();
            $teacher->activate()->save();
            
            // Fire the event manually here
            UserActivated::dispatch($teacher);
            
            $syncTenantUser->execute($teacher->email, RoleType::Teacher->value);
        });

        return ApiResponse::success([
            'teacher' => TeacherData::from($teacher->fresh('teacherProfile')),
        ], "Teacher '{$teacher->first_name} {$teacher->last_name}' has been restored.");
    }

    /**
     * Reset a teacher's password.
     *
     * @subgroup Password
     *
     * @urlParam id string required The teacher UUID.
     *
     * @bodyParam password string required New password (min 8 chars, must contain number). No-example
     * @bodyParam password_confirmation string required Confirm the new password. No-example
     */
    public function resetPassword(ResetUserPassword $resetUserPassword, Request $request, string $id): JsonResponse
    {
        $teacher = User::role(RoleType::Teacher->value)->findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->numbers()],
        ]);

        $resetUserPassword->execute($teacher, $validated['password']);

        return ApiResponse::message('Password reset successfully.');
    }

    /**
     * Download a CSV template for bulk teacher import.
     *
     * @subgroup Import/Export
     */
    public function downloadImportTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, TeacherImportSchema::allHeaders());

            fputcsv($handle, ['John', 'Doe', 'john.doe@example.com', '+2348012345678', 'B.Ed Mathematics', 'TCH/2026/001']);
            fputcsv($handle, ['Jane', 'Smith', 'jane.smith@school.edu', '', 'M.Sc Physics', '']);

            fclose($handle);
        }, 'teacher_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $this->authorize('importTeachers', User::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'dry_run' => ['required', 'in:true,false,1,0'],
            'overwrite_existing' => ['nullable', 'in:skip,update'],
        ]);

        $dryRun = filter_var($validated['dry_run'], FILTER_VALIDATE_BOOLEAN);

        $file = $request->file('file');
        $path = $file->getRealPath();

        if ($dryRun) {
            $result = app(ImportTeachers::class)->execute($validated, $path, true);

            return $this->buildImportResponse($result, true);
        }

        $importJobId = Str::uuid()->toString();
        $central = config('tenancy.database.central_connection');

        DB::connection($central)->table('import_jobs')->insert([
            'id' => $importJobId,
            'tenant_id' => tenant('id'),
            'type' => 'teacher',
            'status' => 'pending',
            'file_contents' => file_get_contents($path),
            'meta' => json_encode(collect($validated)->except(['file', 'dry_run'])->toArray()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            ImportTeachersJob::dispatch($importJobId);
        } catch (\Throwable $e) {
            // Row already durably persisted as 'pending' — imports:recover-stuck
            // sweep picks it up on next scheduled run even if dispatch failed now.
            Log::error('ImportTeachersJob dispatch failed, row will be recovered by scheduled sweep', [
                'import_job_id' => $importJobId,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::message('Teacher import queued. You will be notified when it finishes.', 202);
    }

    private function buildImportResponse(ImportResult $result, bool $dryRun): JsonResponse
    {
        if ($result->getMissingHeaders() !== []) {
            return ApiResponse::error(
                $result->getMessage() ?? 'Missing required columns.',
                422,
                ['missing_headers' => $result->getMissingHeaders()],
            );
        }

        if ($result->getErrors() !== []) {
            return ApiResponse::error(
                $result->getMessage() ?? 'Row validation failed.',
                422,
                $result->getErrors(),
            );
        }

        $status = $dryRun ? 200 : 201;

        return ApiResponse::success(
            $result->toResponseData($dryRun),
            $result->getMessage() ?? ($dryRun ? 'Preview complete.' : 'Import complete.'),
            $status,
        );
    }
}
