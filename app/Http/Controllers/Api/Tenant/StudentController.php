<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Student\StudentAction;
use App\Data\Results\ImportResult;
use App\Data\Schemas\StudentImportSchema;
use App\Data\Student\StudentData;
use App\Events\ActivityFeedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreStudentRequest;
use App\Http\Requests\Tenant\UpdateStudentRequest;
use App\Models\Tenant\User;
use App\Queries\StudentQuery;
use App\Services\Auth\PasswordResetService;
use App\Services\StudentImportService;
use App\Services\TenantUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Student Roster
 * * APIs for managing student enrollments, profiles, and class placements.
 */
class StudentController extends Controller
{
    /**
     * List students with optional filters.
     *
     * @subgroup Student Management
     *
     * @queryParam status string Filter by status: active, inactive, all (default: active). No-example
     * @queryParam search string Search by name, email, or admission number. No-example
     * @queryParam class_level_id string Filter by class level UUID. No-example
     * @queryParam class_arm_id string Filter by class arm UUID. No-example
     */
    public function index(Request $request, StudentQuery $queries): JsonResponse
    {
        $status = $request->query('status', 'active');

        $students = $queries->forList()
            ->tap(fn ($q) => $queries->search($q, $request->query('search')))
            ->tap(fn ($q) => $queries->filterByClass($q, $request->class_level_id, $request->class_arm_id))
            ->withStatus($status)
            ->orderBy('last_name')
            ->paginate(50);

        return ApiResponse::paginated(
            $students,
            'Students retrieved successfully.',
            StudentData::collect($students->getCollection())
        );
    }

    /**
     * Get a single student with their profile.
     *
     * @subgroup Student Management
     *
     * @urlParam id string required The student UUID.
     */
    public function show(string $id): JsonResponse
    {
        $student = User::role('student')
            ->with(['studentProfile.classLevel', 'studentProfile.classArm'])
            ->findOrFail($id);

        return ApiResponse::success(StudentData::from($student), 'Student retrieved successfully.');
    }

    /**
     * Create a new student.
     *
     * @subgroup Student Management
     *
     * @bodyParam first_name string required Student's first name. Example: "John"
     * @bodyParam last_name string required Student's last name. Example: "Doe"
     * @bodyParam email string nullable Student email. No-example
     * @bodyParam phone string nullable Phone number. No-example
     * @bodyParam class_level_id string required Class level UUID. No-example
     * @bodyParam class_arm_id string required Class arm UUID. No-example
     * @bodyParam admission_number string nullable Unique admission number. No-example
     * @bodyParam date_of_birth string nullable Date of birth (Y-m-d). No-example
     * @bodyParam gender string nullable Gender: male, female, other. No-example
     * @bodyParam guardian_email string nullable Guardian email address. No-example
     */
    public function store(StoreStudentRequest $request, StudentAction $action): JsonResponse
    {
        $result = $action->create($request->validated());

        broadcast(new ActivityFeedEvent(
            channelType: 'school_admin',
            channelId: tenant('id'),
            action: 'student.created',
            description: "Student {$result['user']->first_name} {$result['user']->last_name} added.",
            meta: ['student_id' => $result['user']->id],
        ))->toOthers();

        return ApiResponse::created([
            'student' => StudentData::from($result['user']
                ->load(['studentProfile.classLevel', 'studentProfile.classArm'])),
            'login_credentials' => [
                'admission_number' => $result['user']->studentProfile->admission_number,
                'default_password' => $result['password'],
            ],
        ], 'Student created.');
    }

    /**
     * Update a student's information.
     *
     * @subgroup Student Management
     *
     * @urlParam id string required The student UUID.
     *
     * @bodyParam first_name string First name. No-example
     * @bodyParam last_name string Last name. No-example
     * @bodyParam email string nullable Email. No-example
     * @bodyParam phone string nullable Phone. No-example
     * @bodyParam class_level_id string Class level UUID. No-example
     * @bodyParam class_arm_id string nullable Class arm UUID. No-example
     * @bodyParam admission_number string nullable Admission number. No-example
     * @bodyParam date_of_birth string nullable Date of birth. No-example
     * @bodyParam gender string nullable Gender. No-example
     * @bodyParam guardian_email string nullable Guardian email. No-example
     */
    public function update(UpdateStudentRequest $request, string $id, StudentAction $action): JsonResponse
    {
        $result = $action->update($request->validated(), $id);

        return ApiResponse::success(
            StudentData::from($result->load(['studentProfile.classLevel', 'studentProfile.classArm'])),
            'Student updated successfully.'
        );
    }

    /**
     * Reassign a student to a different class.
     *
     * @subgroup Class Assignments
     *
     * @urlParam id string required The student UUID.
     *
     * @bodyParam class_level_id string required New class level UUID. No-example
     * @bodyParam class_arm_id string nullable New class arm UUID. No-example
     */
    public function reassignClass(Request $request, string $id, StudentAction $action): JsonResponse
    {
        $validated = $request->validate([
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'class_arm_id' => ['nullable', 'uuid', 'exists:class_arms,id'],
        ]);

        $result = $action->update($validated, $id);

        return ApiResponse::success([
            'student' => StudentData::from($result->load(['studentProfile.classLevel', 'studentProfile.classArm'])),
        ], 'Student reassigned.');
    }

    /**
     * Revoke a student's access and soft-delete their account.
     *
     * @subgroup Student Status
     *
     * @urlParam id string required The student UUID.
     */
    public function revoke(TenantUserService $tenantUserService, string $id): JsonResponse
    {
        $student = User::role('student')->findOrFail($id);

        DB::transaction(function () use ($student, $tenantUserService) {
            $student->update(['is_active' => false]);
            $student->tokens()->delete();

            $student->studentProfile()->update([
                'class_arm_id' => null,
            ]);
            $tenantUserService->removeFromCentralIndex($student->email);
            $student->delete();
        });

        return ApiResponse::message('Student revoked.');
    }

    /**
     * Restore a previously revoked student.
     *
     * @subgroup Student Status
     *
     * @urlParam id string required The student UUID.
     */
    public function restore(TenantUserService $tenantUserService, string $id): JsonResponse
    {
        $student = User::withTrashed()->role('student')->findOrFail($id);

        if (! $student->trashed()) {
            return ApiResponse::error('This student is already active and has not been deleted.', 422);
        }

        $student->restore();
        $student->update(['is_active' => true]);
        $tenantUserService->updateCentralIndex($student->email, 'student');

        return ApiResponse::success([
            'student' => StudentData::from($student->load(['studentProfile.classLevel', 'studentProfile.classArm'])),
        ], "Student '{$student->first_name} {$student->last_name}' has been restored.");
    }

    /**
     * Permanently delete a student record.
     *
     * @subgroup Student Status
     *
     * @urlParam id string required The student UUID.
     */
    public function destroy(TenantUserService $tenantUserService, string $id): JsonResponse
    {
        $student = User::withTrashed()->role('student')->findOrFail($id);

        DB::transaction(function () use ($student, $tenantUserService) {
            $student->studentProfile()->delete();
            $tenantUserService->removeFromCentralIndex($student->email);
            $student->syncRoles([]);
            $student->forceDelete();
        });

        return ApiResponse::message('Student permanently deleted.');
    }

    /**
     * Reset passwords for all students in a class.
     *
     * @subgroup Bulk Operations
     *
     * @bodyParam class_level_id string required Class level UUID to target. No-example
     * @bodyParam class_arm_id string nullable Class arm UUID to target. No-example
     */
    public function bulkResetPasswords(PasswordResetService $passwordResetService, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'class_arm_id' => ['nullable', 'uuid', 'exists:class_arms,id'],
        ]);

        $query = User::role('student')
            ->whereHas('studentProfile', function ($query) use ($validated) {
                $query->where('class_level_id', $validated['class_level_id'])
                    ->when($validated['class_arm_id'] ?? null, fn ($q) => $q->where('class_arm_id', $validated['class_arm_id']));
            })->with('studentProfile');

        $reset = 0;

        $newPassword = config('app.student_default_password');

        $query->chunkById(200, function ($students) use ($passwordResetService, &$reset, $newPassword) {
            foreach ($students as $student) {
                $passwordResetService->resetPasswordForUser($student, $newPassword);
                $reset++;
            }
        });

        return ApiResponse::success([
            'students_reset' => $reset,
        ], "Passwords reset for {$reset} students.");
    }

    /**
     * Export students as a CSV file.
     *
     * @subgroup Import/Export
     *
     * @queryParam class_level_id string Filter by class level UUID. No-example
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = User::role('student')
            ->with(['studentProfile.classLevel', 'studentProfile.classArm'])
            ->when($request->class_level_id, fn ($q) => $q->whereHas('studentProfile', fn ($p) => $p->where('class_level_id', $request->class_level_id)));

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students_'.now()->format('Y-m-d').'.csv"',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Admission Number', 'First Name', 'Last Name',
                'Email', 'Phone', 'Class Level', 'Class Arm', 'Gender', 'Date of Birth',
                'Guardian Email',
            ]);

            $query->chunk(200, function ($students) use ($handle) {
                foreach ($students as $student) {
                    $profile = $student->studentProfile;
                    fputcsv($handle, [
                        $profile?->admission_number,
                        $student->first_name,
                        $student->last_name,
                        $student->email,
                        $student->phone,
                        $profile?->classLevel?->name,
                        $profile?->classArm?->name,
                        $profile?->gender,
                        $profile?->date_of_birth?->format('Y-m-d'),
                        $profile?->guardian_email,
                    ]);
                }
            });

            fclose($handle);
        }, 'students.csv', $headers);
    }

    /**
     * Download a CSV template for bulk student import.
     *
     * @subgroup Import/Export
     */
    public function downloadImportTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, StudentImportSchema::allHeaders());

            fputcsv($handle, ['John', 'Doe', 'john.doe@example.com', '+2348012345678', 'STU/2026/0001', 'JSS 1', 'A', '2010-03-15', 'male', '']);
            fputcsv($handle, ['Jane', 'Smith', '', '', '', 'SSS 2', 'B', '2008-07-22', 'female', '']);

            fclose($handle);
        }, 'student_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Import students from a CSV file.
     *
     * @subgroup Import/Export
     *
     * @bodyParam file file required The CSV file (max 5MB). No-example
     * @bodyParam dry_run string required Set to "true" to preview without saving. No-example
     * @bodyParam overwrite_existing string nullable How to handle existing records: skip, update. No-example
     * @bodyParam class_level_id string nullable Default class level UUID for new students. No-example
     */
    public function importCsv(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'dry_run' => ['required', 'in:true,false,1,0'],
            'overwrite_existing' => ['nullable', 'in:skip,update'],
            'class_level_id' => ['nullable', 'uuid', 'exists:class_levels,id'],
        ]);

        $dryRun = filter_var($validated['dry_run'], FILTER_VALIDATE_BOOLEAN);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $result = app(StudentImportService::class)->import($validated, $path, $dryRun);

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
