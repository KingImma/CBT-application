<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Teacher\TeacherAction;
use App\Data\Teacher\TeacherData;
use App\Events\ActivityFeedEvent;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\User;
use App\Services\Auth\PasswordResetService;
use App\Services\TenantUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $teachers = User::role('teacher')
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'is_active')
            ->with([
                'teacherProfile',
                'teacherAssignments.subject',
                'teacherAssignments.classLevel',
                'assignedClasses.classLevel',
                'assignedClasses.subjects',
            ])
            ->search($search)
            ->withStatus($status)
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
    public function store(TeacherAction $action, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'staff_id' => ['nullable', 'string', 'max:50', 'unique:teacher_profiles,staff_id'],
            'class_level_id' => ['nullable', 'uuid', 'exists:class_levels,id'],
        ]);

        $result = $action->create($validated);

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
        $teacher = User::role('teacher')->with([
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
        $teacher = User::role('teacher')->findOrFail($id);

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
        $teacher = User::role('teacher')->findOrFail($id);

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

        $merged = $subjectTeacherSubjects->concat(
            $classTeacherSubjects->reject(fn ($classTeacherSubject) => $subjectTeacherSubjects->contains(
                fn ($subjectTeacherSubject) => $subjectTeacherSubject['subject']->id === $classTeacherSubject['subject']->id
                    && $subjectTeacherSubject['class_level']->id === $classTeacherSubject['class_level']->id
            )
            )
        )->values();

        return ApiResponse::success($merged, 'Teacher subjects retrieved.');
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
    public function update(TeacherAction $action, Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$id],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'qualification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'staff_id' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:teacher_profiles,staff_id,'.$id],
            'class_level_id' => ['sometimes', 'nullable', 'uuid', 'exists:class_levels,id'],
        ]);

        $teacher = $action->update($validated, $id);

        return ApiResponse::success(TeacherData::from($teacher), 'Teacher updated successfully.');
    }

    /**
     * Revoke a teacher's access and soft-delete their account.
     *
     * @subgroup Teacher Status
     *
     * @urlParam id string required The teacher UUID.
     */
    public function revoke(TenantUserService $tenantUserService, string $id): JsonResponse
    {
        $teacher = User::role('teacher')->findOrFail($id);

        DB::transaction(function () use ($teacher, $tenantUserService) {
            $teacher->update(['is_active' => false]);
            $teacher->tokens()->delete();

            TeacherSubjectAssignment::where('user_id', $teacher->id)->delete();
            $tenantUserService->removeFromCentralIndex($teacher->email);
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
    public function destroy(TenantUserService $tenantUserService, string $id): JsonResponse
    {
        $teacher = User::withTrashed()->role('teacher')->findOrFail($id);

        DB::transaction(function () use ($teacher, $tenantUserService) {
            $teacher->teacherProfile()->delete();
            TeacherSubjectAssignment::where('user_id', $teacher->id)->delete();
            $tenantUserService->removeFromCentralIndex($teacher->email);
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
    public function restore(TenantUserService $tenantUserService, string $id): JsonResponse
    {
        $teacher = User::withTrashed()->role('teacher')->findOrFail($id);

        if (! $teacher->trashed()) {
            return ApiResponse::error('This teacher is already active and has not been deleted.', 422);
        }

        $teacher->restore();
        $teacher->update(['is_active' => true]);
        $tenantUserService->updateCentralIndex($teacher->email, 'teacher');

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
    public function resetPassword(PasswordResetService $passwordResetService, Request $request, string $id): JsonResponse
    {
        $teacher = User::role('teacher')->findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->numbers()],
        ]);

        $passwordResetService->resetPasswordForUser($teacher, $validated['password']);

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

    /**
     * Import teachers from a CSV file.
     *
     * @subgroup Import/Export
     *
     * @bodyParam file file required The CSV file (max 5MB). No-example
     * @bodyParam dry_run string required Set to "true" to preview without saving. No-example
     * @bodyParam overwrite_existing string nullable How to handle existing records: skip, update. No-example
     */
    public function importCsv(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'dry_run' => ['required', 'in:true,false,1,0'],
            'overwrite_existing' => ['nullable', 'in:skip,update'],
        ]);

        $dryRun = filter_var($validated['dry_run'], FILTER_VALIDATE_BOOLEAN);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $result = app(TeacherImportService::class)->import($validated, $path, $dryRun);

        return $this->buildImportResponse($result, $dryRun);
    }

    private function buildImportResponse(ImportResult $result, bool $dryRun): JsonResponse
    {
        if ($result->missingHeaders !== []) {
            return ApiResponse::error(
                $result->message ?? 'Missing required columns.',
                422,
                ['missing_headers' => $result->missingHeaders],
            );
        }

        if ($result->errors !== []) {
            return ApiResponse::error(
                $result->message ?? 'Row validation failed.',
                422,
                $result->errors,
            );
        }

        $status = $dryRun ? 200 : 201;

        return ApiResponse::success(
            $result->toResponseData($dryRun),
            $result->message ?? ($dryRun ? 'Preview complete.' : 'Import complete.'),
            $status,
        );
    }
}
