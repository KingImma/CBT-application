<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Contracts\CreatesTeacher;
use App\Actions\Contracts\UpdatesTeacher;
use App\Http\Controllers\Api\Tenant\Concerns\TogglesUserActive;
use App\Http\Controllers\Controller;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\User;
use App\Services\PasswordService;
use App\Services\TenantUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class TeacherController extends Controller
{
    use TogglesUserActive;

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');
        $search = $request->query('search');

        $teachers = User::role('teacher')
            ->with('teacherProfile')
            ->search($search)
            ->withStatus($status)
            ->orderBy('last_name')
            ->paginate(20);

        return ApiResponse::paginated($teachers, 'Teachers retrieved successfully.');
    }

    public function store(CreatesTeacher $action, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'staff_id' => ['nullable', 'string', 'max:50', 'unique:teacher_profiles,staff_id'],
        ]);

        $result = $action->execute($validated);

        // TODO: dispatch SendTeacherWelcomeEmail job with $result['password']

        return ApiResponse::created([
            'teacher' => $result['user']->load('teacherProfile'),
            'temporary_password' => $result['password'],
        ], 'Teacher created.');
    }

    public function show(string $id): JsonResponse
    {
        // Now finding by USER ID, not Profile ID
        $teacher = User::role('teacher')->with([
            'teacherProfile',
            // Update these relations if they are defined on the User model
            'teacherAssignments.subject',
            'teacherAssignments.classLevel',
        ])->findOrFail($id);

        return ApiResponse::success($teacher, 'Teacher retrieved successfully.');
    }

    public function update(UpdatesTeacher $action, Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$id],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'qualification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'staff_id' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:teacher_profiles,staff_id,'.$id],
        ]);

        $teacher = $action->execute($validated, $id);

        return ApiResponse::success($teacher, 'Teacher updated successfully.');
    }

    public function destroy(TenantUserService $tenantUserService, string $id): JsonResponse
    {
        $teacher = User::role('teacher')->findOrFail($id);

        DB::transaction(function () use ($teacher, $tenantUserService) {
            $teacher->update(['is_active' => false]);
            $teacher->tokens()->delete();

            TeacherSubjectAssignment::where('user_id', $teacher->id)->delete();
            $tenantUserService->removeFromCentralIndex($teacher->email);
            $teacher->delete();
        });

        return ApiResponse::message('Teacher permanently archived.');
    }

    public function restore(TenantUserService $tenantUserService, string $id): JsonResponse
    {
        $teacher = User::withTrashed()->role('teacher')->findOrFail($id);

        if (! $teacher->trashed()) {
            return ApiResponse::error('This teacher is already active and has not been deleted.', 422);
        }

        $teacher->restore();
        $tenantUserService->updateCentralIndex($teacher->email, 'teacher');

        return ApiResponse::success([
            'teacher' => $teacher->fresh('teacherProfile'),
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
}
