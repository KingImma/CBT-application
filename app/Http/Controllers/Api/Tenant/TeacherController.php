<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\TeacherProfile;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');
        $search = $request->query('search');

        $teachers = User::role('teacher')
            ->with('teacherProfile')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'ilike', "%{$search}%")
                      ->orWhere('last_name', 'ilike', "%{$search}%")
                      ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($status === 'archived', fn ($query) => $query->onlyTrashed())
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'all', fn ($query) => $query->withTrashed())
            ->orderBy('last_name')
            ->paginate(20);

        return response()->json($teachers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'staff_id'      => ['nullable', 'string', 'max:50', 'unique:teacher_profiles,staff_id'],
        ]);

        $password = Str::random(10);

        // Wrap in transaction to ensure profile and user create together
        $user = DB::transaction(function () use ($validated, $password) {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name'  => $validated['last_name'],
                'email'      => $validated['email'],
                'password'   => Hash::make($password),
                'phone'      => $validated['phone'] ?? null,
                'is_active'  => true,
            ]);

            $user->assignRole('teacher');
            
            DB::connection(config('tenancy.database.central_connection'))
                ->table('tenant_user_index')
                ->updateOrInsert(
                    ['email' => $user->email, 'tenant_id' => tenant('id')],
                    ['role' => 'teacher', 'updated_at' => now(), 'created_at' => now()]
                );

            $user->teacherProfile()->create([
                'qualification' => $validated['qualification'] ?? null,
                'staff_id'      => $validated['staff_id'] ?? $this->generateStaffId(),
            ]);

            return $user;
        });

        // TODO: dispatch SendTeacherWelcomeEmail job with $password

        return response()->json([
            'message'            => 'Teacher created.',
            'teacher'            => $user->load('teacherProfile'),
            'temporary_password' => $password, 
        ], 201);
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

        return response()->json($teacher);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $teacher = User::role('teacher')->findOrFail($id);

        $validated = $request->validate([
            'first_name'    => ['sometimes', 'string', 'max:100'],
            'last_name'     => ['sometimes', 'string', 'max:100'],
            'email'         => ['sometimes', 'email', 'unique:users,email,' . $teacher->id],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:20'],
            'qualification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'staff_id'      => ['sometimes', 'nullable', 'string', 'max:50', 'unique:teacher_profiles,staff_id,' . $teacher->teacherProfile?->id],
        ]);

        $teacher->update(collect($validated)->only(['first_name', 'last_name', 'email', 'phone'])->toArray());
        
        // Use updateOrCreate in case the profile got deleted or never existed
        $teacher->teacherProfile()->updateOrCreate(
            ['user_id' => $teacher->id],
            collect($validated)->only(['qualification', 'staff_id'])->toArray()
        );

        return response()->json($teacher->fresh('teacherProfile'));
    }

    public function toggleActive(string $id): JsonResponse
    {
        $teacher = User::role('teacher')->findOrFail($id);

        $teacher->update(['is_active' => ! $teacher->is_active]);
        
        // BUG FIX: You previously had if ($teacher->is_active). 
        // This wiped tokens when activating them. We want to wipe them when deactivating.
        if (! $teacher->is_active) {
            $teacher->tokens()->delete();
        }

        return response()->json([
            'message'   => $teacher->is_active ? 'Teacher activated.' : 'Teacher deactivated.',
            'is_active' => $teacher->is_active,
        ]);
    }
    
    public function destroy(string $id): JsonResponse
    {
        $teacher = User::role('teacher')->findOrFail($id);

        DB::transaction(function () use ($teacher) {
            $teacher->update(['is_active' => false]);
            $teacher->tokens()->delete();
            
            \App\Models\Tenant\TeacherSubjectAssignment::where('user_id', $teacher->id)->delete();
            $teacher->delete(); 
        });

        return response()->json(["message" => "Teacher permanently archived."]);
    }
    
    public function restore(string $id): JsonResponse
    {
        $teacher = User::withTrashed()->role('teacher')->findOrFail($id);

        if (! $teacher->trashed()) {
            return response()->json([
                "message" => "This teacher is already active and has not been deleted."
            ], 422);
        }

        $teacher->restore();

        return response()->json([
            "message" => "Teacher '{$teacher->first_name} {$teacher->last_name}' has been restored.",
            "teacher" => $teacher->fresh('teacherProfile')
        ]);
    }

    public function resetPassword(string $id): JsonResponse
    {
        $teacher = User::role('teacher')->findOrFail($id);
        $password = Str::random(10);

        $teacher->update(['password' => Hash::make($password)]);

        // TODO: dispatch email with new password

        return response()->json([
            'message'            => 'Password reset.',
            'temporary_password' => $password,
        ]);
    }
    
    private function generateStaffId(): string
    {
        $currentYear = date('Y');
        $teacherCount = TeacherProfile::whereYear('created_at', $currentYear)->count();
        $nextSequence = $teacherCount + 1;
        $formattedSequence = str_pad((string)$nextSequence, 3, '0', STR_PAD_LEFT);

        return "TCH/{$currentYear}/{$formattedSequence}";
    }
}