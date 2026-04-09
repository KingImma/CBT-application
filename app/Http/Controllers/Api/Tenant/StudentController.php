<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Student\CreateStudentAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Actions\Tenants\Student\UpdateStudentAction;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $students = StudentProfile::with(['user', 'classLevel', 'classArm'])
            ->when($request->search, fn ($q) =>
                $q->whereHas('user', fn ($u) =>
                    $u->where('first_name', 'ilike', "%{$request->search}%")
                      ->orWhere('last_name', 'ilike', "%{$request->search}%")
                      ->orWhere('email', 'ilike', "%{$request->search}%")
                )->orWhere('registration_number', 'ilike', "%{$request->search}%")
            )
            ->when($request->class_level_id, fn ($q) =>
                $q->where('class_level_id', $request->class_level_id)
            )
            ->when($request->class_arm_id, fn ($q) =>
                $q->where('class_arm_id', $request->class_arm_id)
            )
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($students);
    }
    
    public function show(string $id): JsonResponse
    {
        return response()->json(
            StudentProfile::with(['user', 'classLevel', 'classArm'])->findOrFail($id)
        );
    }

    public function store(StoreStudentRequest $request, CreateStudentAction $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        return response()->json([
            'message'  => 'Student created.',
            'student'  => $result['profile']->load(['user', 'classLevel', 'classArm']),
            'login_credentials' => [
                'registration_number' => $result['profile']->registration_number,
                'default_password'    => $result['password'],
            ],
        ], 201);
    }

    public function update(UpdateStudentRequest $request, string $id, UpdateStudentAction $action): JsonResponse
    {
        $result = $action->execute($request->validated(), $id);

        return response()->json($result['profile']->fresh(['user', 'classLevel', 'classArm']));
    }

    public function reassignClass(Request $request, string $id, UpdateStudentAction $action): JsonResponse
    {
        $result = $action->execute($request->validate([
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'class_arm_id'   => ['nullable', 'uuid', 'exists:class_arms,id'],
        ]), $id);

        return response()->json([
            'message' => 'Student reassigned.',
            'student' => $result['profile']->fresh(['classLevel', 'classArm']),
        ]);
    }

    public function toggleActive(string $id, UpdateStudentAction $action): JsonResponse
    {
        $result = $action->execute([], $id);
        $user   = $result['profile']->user; 

        $user->update(['is_active' => ! $user->is_active]);     

        return response()->json([
            'message'   => $user->is_active ? 'Student activated.' : 'Student deactivated.',
            'is_active' => $user->is_active,
        ]);
    }

    public function bulkResetPasswords(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'class_arm_id'   => ['nullable', 'uuid', 'exists:class_arms,id'],
        ]);

        $profiles = StudentProfile::with('user')
            ->where('class_level_id', $validated['class_level_id'])
            ->when($validated['class_arm_id'] ?? null, fn ($q) =>
                $q->where('class_arm_id', $validated['class_arm_id'])
            )
            ->get();

        $reset = 0;

        foreach ($profiles as $profile) {
            // Reset to registration number as default password
            $profile->user->update([
                'password' => Hash::make($profile->registration_number),
            ]);
            $reset++;
        }

        return response()->json([
            'message'       => "Passwords reset for {$reset} students.",
            'students_reset' => $reset,
        ]);
    }

    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = StudentProfile::with(['user', 'classLevel', 'classArm'])
            ->when($request->class_level_id, fn ($q) =>
                $q->where('class_level_id', $request->class_level_id)
            );

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students_' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Registration Number', 'First Name', 'Last Name',
                'Email', 'Class Level', 'Class Arm', 'Gender', 'Date of Birth',
            ]);

            $query->chunk(200, function ($students) use ($handle) {
                foreach ($students as $student) {
                    fputcsv($handle, [
                        $student->registration_number,
                        $student->user->first_name,
                        $student->user->last_name,
                        $student->user->email,
                        $student->classLevel?->name,
                        $student->classArm?->name,
                        $student->gender,
                        $student->date_of_birth?->format('Y-m-d'),
                    ]);
                }
            });

            fclose($handle);
        }, 'students.csv', $headers);
    }

    private function generateRegNumber(): string
    {
        $year  = now()->format('Y');
        $count = StudentProfile::count() + 1;
        return "STU/{$year}/" . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}