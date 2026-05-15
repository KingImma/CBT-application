<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Student\StudentAction;
use App\Http\Controllers\Api\Tenant\Concerns\TogglesUserActive;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Http\Requests\Tenant\StoreStudentRequest;
use App\Http\Requests\Tenant\UpdateStudentRequest;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\User;
use App\Services\PasswordService;
use App\Services\TenantUserService;
use App\Data\Schemas\StudentImportSchema;
use App\Services\StudentImportService;
use App\Support\ApiResponse;
use App\Data\Results\ImportResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\ActivityFeedEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Student Roster
 * * APIs for managing student enrollments, profiles, and class placements.
 */
class StudentController extends Controller
{
    use TogglesUserActive;

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');
        $search = $request->query('search');

        $students = User::role('student')
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'is_active')
            ->with(['studentProfile.classLevel', 'studentProfile.classArm'])

            // 1. Search by Name/Email on the User table, or Reg Number on the Profile
            ->when($search, function ($query) use ($search) {
                $query->search($search, ['first_name', 'last_name', 'email'])
                    ->orWhereHas('studentProfile', fn ($p) => $p->where('admission_number', 'ilike', "%{$search}%")
                    );
            })

            // 2. Filter by Class/Arm via the Profile relation
            ->when($request->class_level_id, fn ($q) => $q->whereHas('studentProfile', fn ($p) => $p->where('class_level_id', $request->class_level_id))
            )
            ->when($request->class_arm_id, fn ($q) => $q->whereHas('studentProfile', fn ($p) => $p->where('class_arm_id', $request->class_arm_id))
            )

            // 3. Apply standard status filters
            ->withStatus($status)

            ->orderBy('last_name')
            ->paginate(50);

        return ApiResponse::paginated(
            $students,
            'Students retrieved successfully.',
            StudentResource::collection($students->getCollection())->resolve($request)
        );
    }

    public function show(string $id): JsonResponse
    {
        $student = User::role('student')
            ->with(['studentProfile.classLevel', 'studentProfile.classArm'])
            ->findOrFail($id);

        return ApiResponse::success(new StudentResource($student), 'Student retrieved successfully.');
    }

    public function store(StoreStudentRequest $request, StudentAction $action): JsonResponse
    {
        $result = $action->create($request->validated());
        
        broadcast(new ActivityFeedEvent(
            channelType: 'school',
            channelId:   tenant('id'),
            action:      'student.created',
            description: "Student {$result['user']->first_name} {$result['user']->last_name} added.",
            meta:        ['student_id' => $result['user']->id],
        ))->toOthers();

        return ApiResponse::created([
            'student' => new StudentResource($result['user']->load(['studentProfile.classLevel', 'studentProfile.classArm'])),
            'login_credentials' => [
                'admission_number' => $result['user']->studentProfile->admission_number,
                'default_password' => $result['password'],
            ],
        ], 'Student created.');
    }

    public function update(UpdateStudentRequest $request, string $id, StudentAction $action): JsonResponse
    {
        $result = $action->update($request->validated(), $id);

        return ApiResponse::success(
            new StudentResource($result->load(['studentProfile.classLevel', 'studentProfile.classArm'])),
            'Student updated successfully.'
        );
    }

    public function reassignClass(Request $request, string $id, StudentAction $action): JsonResponse
    {
        $validated = $request->validate([
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'class_arm_id' => ['nullable', 'uuid', 'exists:class_arms,id'],
        ]);

        $result = $action->update($validated, $id);

        return ApiResponse::success([
            'student' => new StudentResource($result->load(['studentProfile.classLevel', 'studentProfile.classArm'])),
        ], 'Student reassigned.');
    }

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
            'student' => new StudentResource($student->load(['studentProfile.classLevel', 'studentProfile.classArm'])),
        ], "Student '{$student->first_name} {$student->last_name}' has been restored.");
    }

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

    public function bulkResetPasswords(PasswordService $passwordService, Request $request): JsonResponse
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

        $query->chunk(200, function ($students) use ($passwordService, &$reset) {
            foreach ($students as $student) {
                $newPassword = $student->studentProfile->admission_number;
                $passwordService->resetPasswordForUser($student, $newPassword);
                $reset++;
            }
        });

        return ApiResponse::success([
            'students_reset' => $reset,
        ], "Passwords reset for {$reset} students.");
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $query = User::role('student')
            ->with(['studentProfile.classLevel', 'studentProfile.classArm'])
            ->when($request->class_level_id, fn ($q) => $q->whereHas('studentProfile', fn ($p) => $p->where('class_level_id', $request->class_level_id))
            );

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students_'.now()->format('Y-m-d').'.csv"',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Admission Number', 'First Name', 'Last Name',
                'Email', 'Class Level', 'Class Arm', 'Gender', 'Date of Birth',
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

    public function downloadImportTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, StudentImportSchema::allHeaders());

            fputcsv($handle, ['John', 'Doe', 'john.doe@example.com', 'STU/2026/0001', 'JSS 1', 'A', '2010-03-15', 'male', '']);
            fputcsv($handle, ['Jane', 'Smith', '', '', 'SSS 2', 'B', '2008-07-22', 'female', '']);

            fclose($handle);
        }, 'student_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

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
