<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Data\ClassLevel\ClassLevelData;
use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @group Classes & Arms
 * * APIs for managing class structures (e.g., JSS1) and specific arms (e.g., JSS1A), including subject mapping.
 */
class ClassLevelController extends Controller
{
    /**
     * List all class levels with arm and student counts.
     *
     * @subgroup Class Levels
     */
    public function index(): JsonResponse
    {
        $levels = ClassLevel::withCount(['classArms', 'students'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ClassLevelData::collect($levels), 'Class levels retrieved successfully.');
    }

    /**
     * Create a new class level.
     *
     * @subgroup Class Levels
     *
     * @bodyParam name string required Class level name. Example: "JSS 1"
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge(['name' => $this->normalizeName($request->input('name'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('class_levels', 'name')->withoutTrashed()],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $level = ClassLevel::create($validated);

        return ApiResponse::created(ClassLevelData::from($level), 'Class level created.');
    }

    /**
     * Get a single class level with its arms and subjects.
     *
     * @subgroup Class Levels
     *
     * @urlParam id string required The class level UUID.
     */
    public function show(string $id): JsonResponse
    {
        $level = ClassLevel::withCount(['classArms', 'students'])
            ->with(['classArms.assignedTeacher', 'subjects'])
            ->findOrFail($id);

        return ApiResponse::success(ClassLevelData::from($level), 'Class level retrieved successfully.');
    }

    /**
     * Update a class level.
     *
     * @subgroup Class Levels
     *
     * @urlParam id string required The class level UUID.
     *
     * @bodyParam name string Class level name. No-example
     * @bodyParam order int Display order. No-example
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $level = ClassLevel::findOrFail($id);

        if ($request->has('name')) {
            $request->merge(['name' => $this->normalizeName($request->input('name'))]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('class_levels', 'name')->ignore($id)->withoutTrashed()],
            'order' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['name']) && $validated['name'] !== $level->name) {
            $validated['slug'] = Str::slug($validated['name'], '');
        }

        if (empty($validated['order'])) {
            unset($validated['order']);
        }

        $level->update($validated);

        return ApiResponse::success(ClassLevelData::from($level->fresh()), 'Class level updated.');
    }

    /**
     * Delete a class level (soft-deletes if dependencies exist).
     *
     * @subgroup Class Levels
     *
     * @urlParam id string required The class level UUID.
     */
    public function destroy(string $id): JsonResponse
    {
        $level = ClassLevel::withCount(['students', 'exams', 'classArms'])->findOrFail($id);

        $level->delete();

        $parts = [];
        if ($level->students_count > 0) {
            $parts[] = "{$level->students_count} student(s)";
        }
        if ($level->exams_count > 0) {
            $parts[] = "{$level->exams_count} exam(s)";
        }
        if ($level->classArms_count > 0) {
            $parts[] = "{$level->classArms_count} arm(s)";
        }

        $message = $parts !== []
            ? 'Class level soft-deleted (has '.implode(', ', $parts).').'
            : 'Class level deleted.';

        return ApiResponse::message($message);
    }

    /**
     * Get subjects available for a class level with teacher assignments.
     *
     * @subgroup Class Levels
     *
     * @urlParam id string required The class level UUID.
     */
    public function availableSubjects(string $id): JsonResponse
    {
        $level = ClassLevel::findOrFail($id);

        $subjects = $level->subjects()
            ->select('subjects.id', 'subjects.name', 'subjects.code')
            ->with(['teacherAssignments' => fn ($q) => $q
                ->where('class_level_id', $level->id)
                ->with('user:id,first_name,last_name'),
            ])
            ->get()
            ->map(function ($subject) {
                $teacher = $subject->teacherAssignments->first()?->user;

                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'is_compulsory' => (bool) $subject->pivot->is_compulsory,
                    'assigned_teacher' => $teacher ? [
                        'id' => $teacher->id,
                        'first_name' => $teacher->first_name,
                        'last_name' => $teacher->last_name,
                    ] : null,
                ];
            });

        return ApiResponse::success($subjects, 'Available subjects retrieved successfully.');
    }

    /**
     * Sync subjects assigned to a class level.
     *
     * @subgroup Class Levels
     *
     * @urlParam id string required The class level UUID.
     *
     * @bodyParam subject_ids array required Array of subject UUIDs to assign. No-example
     * @bodyParam subject_ids.* string Subject UUID. No-example
     * @bodyParam compulsory_ids array Array of subject UUIDs to mark as compulsory. No-example
     * @bodyParam compulsory_ids.* string Subject UUID. No-example
     */
    public function sync(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'subject_ids' => ['required', 'array'],
            'subject_ids.*' => ['exists:subjects,id'],
            'compulsory_ids' => ['nullable', 'array'],
            'compulsory_ids.*' => ['exists:subjects,id'],
        ]);

        $level = ClassLevel::findOrFail($id);

        $level->subjects()->sync($validated['subject_ids']);

        if (! empty($validated['compulsory_ids'])) {
            foreach ($level->classArms as $arm) {
                foreach ($validated['compulsory_ids'] as $subjectId) {
                    $arm->subjects()->syncWithoutDetaching([
                        $subjectId => [
                            'id' => Str::uuid()->toString(),
                            'is_compulsory' => true,
                        ],
                    ]);
                }
            }
        }

        return ApiResponse::message('Subjects synced successfully.');
    }

    /**
     * Toggle whether a subject is compulsory at this class level.
     *
     * @subgroup Class Levels
     *
     * @urlParam id string required The class level UUID.
     * @urlParam subjectId string required The subject UUID.
     */
    public function toggleCompulsory(string $id, string $subjectId): JsonResponse
    {
        $level = ClassLevel::findOrFail($id);

        $subject = $level->subjects()->where('subject_id', $subjectId)->firstOrFail();

        $newStatus = ! $subject->pivot->is_compulsory;

        $level->subjects()->updateExistingPivot($subjectId, [
            'is_compulsory' => $newStatus,
        ]);

        if ($newStatus) {
            foreach ($level->classArms as $arm) {
                $arm->subjects()->syncWithoutDetaching([
                    $subjectId => [
                        'id' => Str::uuid()->toString(),
                        'is_compulsory' => true,
                    ],
                ]);
            }
        }

        return ApiResponse::success([
            'is_compulsory' => $newStatus,
        ], 'Subject compulsory status updated.');
    }

    /**
     * Assign a teacher to a class level.
     *
     * @subgroup Class Levels
     *
     * @urlParam id string required The class level UUID.
     *
     * @bodyParam teacher_id string required The teacher UUID. No-example
     */
    public function assignTeacher(Request $request, string $id): JsonResponse
    {

        $level = ClassLevel::findOrFail($id);

        $validated = $request->validate([
            'teacher_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $teacher = User::role(RoleType::Teacher->value)->findOrFail($validated['teacher_id']);

        $teacher->teacherProfile()->update(
            ['class_level_id' => $level->id]
        );

        return ApiResponse::success(
            ClassLevelData::from($level->load(['classArms.assignedTeacher', 'subjects'])),
            "Teacher assigned to class level {$level->name} successfully."
        );
    }

    private function normalizeName(?string $name): string
    {
        return trim(strtoupper($name ?? ''));
    }
}
