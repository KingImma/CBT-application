<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Data\Subject\SubjectData;
use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Subject;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\User;
use App\Rules\UniqueNormalized;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Curriculum & Subjects
 * * APIs for managing the academic curriculum and subject topics.
 */
class SubjectController extends Controller
{
    /**
     * List all active subjects with their class levels and teacher assignments.
     *
     * @subgroup Subject Management
     *
     * @queryParam class_level_id string Filter by class level UUID. No-example
     */
    public function index(Request $request): JsonResponse
    {
        $subjects = Subject::with([
            'classLevels',
            'teacherAssignments.user:id,first_name,last_name',
        ])
            ->where('is_active', true)
            ->when($request->class_level_id, fn ($q, $id) => $q->whereHas('classLevels', fn ($q2) => $q2->where('class_level_id', $id)))
            ->orderBy('name')
            ->get();

        return ApiResponse::success(SubjectData::collect($subjects), 'Subjects retrieved successfully.');
    }

    /**
     * Create a new subject.
     *
     * @subgroup Subject Management
     *
     * @bodyParam name string required Subject name. Example: "Mathematics"
     * @bodyParam code string nullable Subject code (unique). Example: "MTH101"
     * @bodyParam class_level_ids array Array of class level UUIDs to assign this subject to. No-example
     * @bodyParam class_level_ids.* string Class level UUID. No-example
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', new UniqueNormalized('subjects')],
            'code' => ['nullable', 'string', 'max:20', 'unique:subjects,code'],
            'class_level_ids' => ['nullable', 'array'],
            'class_level_ids.*' => ['uuid', 'exists:class_levels,id'],
        ]);

        $subject = Subject::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'is_active' => true,
        ]);

        if (! empty($validated['class_level_ids'])) {
            $subject->classLevels()->sync($validated['class_level_ids']);
        }

        return ApiResponse::created(SubjectData::from($subject->load('classLevels')), 'Subject created.');
    }

    /**
     * Get a single subject with its class levels and teacher assignments.
     *
     * @subgroup Subject Management
     *
     * @urlParam id string required The subject UUID.
     */
    public function show(string $id): JsonResponse
    {
        $subject = Subject::with([
            'classLevels',
            'teacherAssignments.user:id,first_name,last_name',
        ])->findOrFail($id);

        return ApiResponse::success(SubjectData::from($subject), 'Subject retrieved successfully.');
    }

    /**
     * Update a subject.
     *
     * @subgroup Subject Management
     *
     * @urlParam id string required The subject UUID.
     *
     * @bodyParam name string Subject name. No-example
     * @bodyParam code string nullable Subject code. No-example
     * @bodyParam is_active boolean Whether the subject is active. No-example
     * @bodyParam class_level_ids array Class level UUIDs to assign. No-example
     * @bodyParam class_level_ids.* string Class level UUID. No-example
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', (new UniqueNormalized('subjects'))->ignore($id)],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'unique:subjects,code,'.$id,
            ],
            'is_active' => ['sometimes', 'boolean'],
            'class_level_ids' => ['sometimes', 'array'],
            'class_level_ids.*' => ['uuid', 'exists:class_levels,id'],
        ]);

        $subject->update(
            collect($validated)->except('class_level_ids')->toArray(),
        );

        if (isset($validated['class_level_ids'])) {
            $subject->classLevels()->sync($validated['class_level_ids']);
        }

        return ApiResponse::success(SubjectData::from($subject->fresh(['classLevels'])), 'Subject updated.');
    }

    /**
     * Deactivate a subject (soft delete).
     *
     * @subgroup Subject Management
     *
     * @urlParam id string required The subject UUID.
     */
    public function destroy(string $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);
        $subject->update(['is_active' => false]);

        return ApiResponse::message('Subject deactivated.');
    }

    /**
     * Assign a teacher to a subject within a class level.
     */
    public function assignTeacher(Request $request, string $id): JsonResponse
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'user_id' => [
                'required',
                'uuid',
                'exists:users,id',
            ],
            'class_level_id' => [
                'required',
                'uuid',
                'exists:class_levels,id',
            ],
            'academic_session_id' => [
                'required',
                'uuid',
                'exists:academic_sessions,id',
            ],
        ]);

        $teacher = User::findOrFail($validated['user_id']);

        if (! $teacher->hasRole(RoleType::Teacher->value)) {
            return ApiResponse::error('Invalid assignment. The selected user must be a teacher.', 422);
        }

        $exists = TeacherSubjectAssignment::where([
            'subject_id' => $subject->id,
            'user_id' => $validated['user_id'],
            'class_level_id' => $validated['class_level_id'],
            'academic_session_id' => $validated['academic_session_id'],
        ])->exists();

        if ($exists) {
            return ApiResponse::error(
                'This teacher is already assigned to this subject for this class level.',
                422
            );
        }

        $assignment = TeacherSubjectAssignment::create([
            'subject_id' => $subject->id,
            'user_id' => $validated['user_id'],
            'class_level_id' => $validated['class_level_id'],
            'academic_session_id' => $validated['academic_session_id'],
        ]);

        // Ensure the subject is linked to the class level pivot
        if (! $subject->classLevels()->where('class_level_id', $validated['class_level_id'])->exists()) {
            $subject->classLevels()->attach($validated['class_level_id'], ['is_compulsory' => false]);
        }

        return ApiResponse::created(
            $assignment->load(['user', 'subject', 'classLevel', 'academicSession']),
            'Teacher assigned to subject.'
        );
    }

    /**
     * Remove a teacher from a subject assignment.
     */
    public function removeTeacher(
        string $id,
        string $assignmentId,
    ): JsonResponse {
        $assignment = TeacherSubjectAssignment::where(
            'subject_id',
            $id,
        )->findOrFail($assignmentId);

        $assignment->delete();

        return ApiResponse::message('Teacher assignment removed.');
    }
}
