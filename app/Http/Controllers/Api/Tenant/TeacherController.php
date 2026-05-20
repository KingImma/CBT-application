<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Teacher\TeacherAction;
use App\Data\Results\ImportResult;
use App\Data\Schemas\TeacherImportSchema;
use App\Events\ActivityFeedEvent;
use App\Http\Controllers\Api\Tenant\Concerns\TogglesUserActive;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherResource;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\User;
use App\Services\PasswordService;
use App\Services\TeacherImportService;
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
    use TogglesUserActive;

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
            TeacherResource::collection($teachers->getCollection())->resolve($request)
        );
    }

    public function store(TeacherAction $action, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'staff_id' => ['nullable', 'string', 'max:50', 'unique:teacher_profiles,staff_id'],
        ]);

        $result = $action->create($validated);

        broadcast(new ActivityFeedEvent(
            channelType: 'school_admin',
            channelId: tenant('id'),
            action: 'teacher.created',
            description: "Teacher {$result['user']->first_name} {$result['user']->last_name} added.",
        ))->toOthers();

        // TODO: dispatch SendTeacherWelcomeEmail job with $result['password']

        return ApiResponse::created([
            'teacher' => new TeacherResource($result['user']->load('teacherProfile')),
            'temporary_password' => $result['password'],
        ], 'Teacher created.');
    }

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

        return ApiResponse::success(new TeacherResource($teacher), 'Teacher retrieved successfully.');
    }

    public function classes(string $id): JsonResponse
    {
        $teacher = User::role('teacher')->findOrFail($id);

        $classes = ClassArm::where('assigned_teacher_id', $teacher->id)
            ->with(['classLevel', 'subjects'])
            ->get();

        return ApiResponse::success($classes, 'Teacher classes retrieved.');
    }

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

    public function update(TeacherAction $action, Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$id],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'qualification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'staff_id' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:teacher_profiles,staff_id,'.$id],
        ]);

        $teacher = $action->update($validated, $id);

        return ApiResponse::success(new TeacherResource($teacher), 'Teacher updated successfully.');
    }

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
            'teacher' => new TeacherResource($teacher->fresh('teacherProfile')),
        ], "Teacher '{$teacher->first_name} {$teacher->last_name}' has been restored.");
    }

    public function resetPassword(PasswordService $passwordService, Request $request, string $id): JsonResponse
    {
        $teacher = User::role('teacher')->findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->numbers()],
        ]);

        $passwordService->resetPasswordForUser($teacher, $validated['password']);

        return ApiResponse::message('Password reset successfully.');
    }

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

        if ($dryRun) {
            $data = [
                'dry_run' => true,
                'total_rows' => $result->totalRows,
                'can_proceed' => true,
            ];

            if ($result->duplicates !== []) {
                $data['duplicates'] = $result->duplicates;
            }

            return ApiResponse::success($data, $result->message ?? 'Preview complete.');
        }

        $data = [
            'imported' => $result->imported,
        ];

        if ($result->skipped > 0) {
            $data['skipped'] = $result->skipped;
        }
        if ($result->updated > 0) {
            $data['updated'] = $result->updated;
        }

        return ApiResponse::success($data, $result->message ?? 'Import complete.', 201);
    }
}
