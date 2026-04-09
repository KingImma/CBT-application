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

class TeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teachers = TeacherProfile::with('user')
            ->when($request->search, fn ($q) =>
                $q->whereHas('user', fn ($u) =>
                    $u->where('first_name', 'ilike', "%{$request->search}%")
                      ->orWhere('last_name', 'ilike', "%{$request->search}%")
                      ->orWhere('email', 'ilike', "%{$request->search}%")
                )
            )
            ->when($request->boolean('active_only'), fn ($q) =>
                $q->whereHas('user', fn ($u) => $u->where('is_active', true))
            )
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($teachers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'email'           => ['required', 'email', 'unique:users,email'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'qualification'   => ['nullable', 'string', 'max:255'],
            'employee_id'     => ['nullable', 'string', 'max:50', 'unique:teacher_profiles,employee_id'],
        ]);

        $password = Str::random(10);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($password),
            'is_active'  => true,
        ]);

        $user->assignRole('teacher');
        
        // In TeacherController@store — after $user->assignRole('teacher')
        \Illuminate\Support\Facades\DB::connection('central')
        ->table('tenant_user_index')
        ->updateOrInsert(
            ['email' => $user->email, 'tenant_id' => tenant('id')],
            ['role' => 'teacher', 'updated_at' => now(), 'created_at' => now()]
        );

        $profile = TeacherProfile::create([
            'user_id'       => $user->id,
            'phone'         => $validated['phone'] ?? null,
            'qualification' => $validated['qualification'] ?? null,
            'employee_id'   => $validated['employee_id'] ?? null,
        ]);

        // TODO: dispatch SendTeacherWelcomeEmail job with $password
        // dispatch(new SendTeacherWelcomeEmail($user, $password));

        return response()->json([
            'message'          => 'Teacher created.',
            'teacher'          => $profile->load('user'),
            'temporary_password' => $password, // remove once email dispatch is wired
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $profile = TeacherProfile::with([
            'user',
            'subjectAssignments.subject',
            'subjectAssignments.classLevel',
        ])->findOrFail($id);

        return response()->json($profile);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $profile = TeacherProfile::with('user')->findOrFail($id);

        $validated = $request->validate([
            'first_name'    => ['sometimes', 'string', 'max:100'],
            'last_name'     => ['sometimes', 'string', 'max:100'],
            'email'         => ['sometimes', 'email', 'unique:users,email,' . $profile->user_id],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:20'],
            'qualification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employee_id'   => ['sometimes', 'nullable', 'string', 'max:50', 'unique:teacher_profiles,employee_id,' . $id],
        ]);

        $profile->user->update(collect($validated)->only(['first_name', 'last_name', 'email'])->toArray());
        $profile->update(collect($validated)->only(['phone', 'qualification', 'employee_id'])->toArray());

        return response()->json($profile->fresh('user'));
    }

    public function toggleActive(string $id): JsonResponse
    {
        $profile = TeacherProfile::with('user')->findOrFail($id);
        $user    = $profile->user;

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'message'   => $user->is_active ? 'Teacher activated.' : 'Teacher deactivated.',
            'is_active' => $user->is_active,
        ]);
    }

    public function resetPassword(string $id): JsonResponse
    {
        $profile  = TeacherProfile::with('user')->findOrFail($id);
        $password = Str::random(10);

        $profile->user->update(['password' => Hash::make($password)]);

        // TODO: dispatch email with new password

        return response()->json([
            'message'            => 'Password reset.',
            'temporary_password' => $password,
        ]);
    }
}