<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Contracts\CreatesStudent;
use App\Actions\Contracts\UpdatesStudent;
use App\Http\Controllers\Api\Tenant\Concerns\TogglesUserActive;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreStudentRequest;
use App\Http\Requests\Tenant\UpdateStudentRequest;
use App\Models\Tenant\User;
use App\Services\PasswordService;
use App\Services\StudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    use TogglesUserActive;

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');
        $search = $request->query('search');

        $students = User::role('student')
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

        return response()->json($students);
    }

    public function show(string $id): JsonResponse
    {
        $student = User::role('student')
            ->with(['studentProfile.classLevel', 'studentProfile.classArm'])
            ->findOrFail($id);

        return response()->json($student);
    }

    public function store(StoreStudentRequest $request, CreatesStudent $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        return response()->json([
            'message' => 'Student created.',
            'student' => $result['user']->load(['studentProfile.classLevel', 'studentProfile.classArm']),
            'login_credentials' => [
                'admission_number' => $result['user']->studentProfile->admission_number,
                'default_password' => $result['password'],
            ],
        ], 201);
    }

    public function update(UpdateStudentRequest $request, string $id, UpdatesStudent $action): JsonResponse
    {
        // Ensure action expects the User ID
        $result = $action->execute($request->validated(), $id);

        return response()->json(
            $result['user']->load(['studentProfile.classLevel', 'studentProfile.classArm'])
        );
    }

    public function reassignClass(Request $request, string $id, UpdatesStudent $action): JsonResponse
    {
        $validated = $request->validate([
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'class_arm_id' => ['nullable', 'uuid', 'exists:class_arms,id'],
        ]);

        // Handled cleanly by your action
        $result = $action->execute($validated, $id);

        return response()->json([
            'message' => 'Student reassigned.',
            'student' => $result['user']->load(['studentProfile.classLevel', 'studentProfile.classArm']),
        ]);
    }

    public function bulkResetPasswords(PasswordService $passwordService, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'class_arm_id' => ['nullable', 'uuid', 'exists:class_arms,id'],
        ]);

        $students = User::role('student')
            ->whereHas('studentProfile', function ($query) use ($validated) {
                $query->where('class_level_id', $validated['class_level_id'])
                    ->when($validated['class_arm_id'] ?? null, fn ($q) => $q->where('class_arm_id', $validated['class_arm_id'])
                    );
            })->with('studentProfile')->get();

        $reset = 0;

        foreach ($students as $student) {
            $newPassword = $student->studentProfile->admission_number;
            $passwordService->resetPasswordForUser($student, $newPassword);
            $reset++;
        }

        return response()->json([
            'message' => "Passwords reset for {$reset} students.",
            'students_reset' => $reset,
        ]);
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

            fputcsv($handle, [
                'first_name',
                'last_name',
                'email',
                'admission_number',
                'class_level',
                'class_arm',
                'date_of_birth',
                'gender',
            ]);

            fputcsv($handle, ['John', 'Doe', 'john.doe@example.com', 'STU/2026/0001', 'JSS 1', 'A', '2010-03-15', 'male']);
            fputcsv($handle, ['Jane', 'Smith', '', '', 'SSS 2', 'B', '2008-07-22', 'female']);

            fclose($handle);
        }, 'student_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'overwrite_existing' => ['nullable', 'in:update,skip'],
            'class_level_id' => ['nullable', 'uuid', 'exists:class_levels,id'],
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $result = app(StudentImportService::class)->import($validated, $path);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        $statusCode = $result['failed'] > 0 || $result['duplicates_found'] > 0 ? 207 : 201;

        return response()->json([
            'message' => "Import complete. {$result['imported']} imported, {$result['duplicates_found']} duplicates, {$result['failed']} failed.",
            'total_rows' => $result['total_rows'],
            'imported' => $result['imported'],
            'duplicates_found' => $result['duplicates_found'],
            'failed' => $result['failed'],
            'duplicates' => $result['duplicates'],
            'errors' => $result['errors'],
        ], $statusCode);
    }
}
