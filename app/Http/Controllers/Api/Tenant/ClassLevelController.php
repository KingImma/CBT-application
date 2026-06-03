<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Classes & Arms
 * * APIs for managing class structures (e.g., JSS1) and specific arms (e.g., JSS1A), including subject mapping.
 */
class ClassLevelController extends Controller
{
    public function index(): JsonResponse
    {
        $levels = ClassLevel::withCount(['classArms', 'students'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success($levels, 'Class levels retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:class_levels,name'],
        ]);

        // Auto-generate the slug based on the name
        $validated['slug'] = Str::slug($validated['name']);

        $level = ClassLevel::create($validated);

        return ApiResponse::created($level, 'Class level created.');
    }

    public function show(string $id): JsonResponse
    {
        $level = ClassLevel::withCount(['classArms', 'students'])
            ->with(['classArms.assignedTeacher', 'subjects'])
            ->findOrFail($id);

        return ApiResponse::success($level, 'Class level retrieved successfully.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $level = ClassLevel::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', 'unique:class_levels,name,'.$id],
            'order' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['name']) && $validated['name'] !== $level->name) {
            $validated['slug'] = Str::slug($validated['name'], '');
        }

        if (empty($validated['order'])) {
            unset($validated['order']);
        }

        $level->update($validated);

        return ApiResponse::success($level->fresh(), 'Class level updated.');
    }

    public function destroy(string $id): JsonResponse
    {
        $level = ClassLevel::withCount('students')->findOrFail($id);

        // Prevent deletion if students are assigned — data integrity
        if ($level->students_count > 0) {
            return ApiResponse::error("Cannot delete — {$level->students_count} student(s) are assigned to this class level.", 422);
        }

        $level->delete();

        return ApiResponse::message('Class level deleted.');
    }

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
            $level->classArms->each(function ($arm) use ($validated) {
                foreach ($validated['compulsory_ids'] as $subjectId) {
                    $arm->subjects()->syncWithoutDetaching([
                        $subjectId => [
                            'id' => Str::uuid()->toString(),
                            'is_compulsory' => true,
                        ],
                    ]);
                }
            });
        }

        return ApiResponse::message('Subjects synced successfully.');
    }

    public function toggleCompulsory(string $id, string $subjectId): JsonResponse
    {
        $level = ClassLevel::findOrFail($id);

        $subject = $level->subjects()->where('subject_id', $subjectId)->firstOrFail();

        $newStatus = ! $subject->pivot->is_compulsory;

        $level->subjects()->updateExistingPivot($subjectId, [
            'is_compulsory' => $newStatus,
        ]);

        if ($newStatus) {
            $level->classArms->each(function ($arm) use ($subjectId) {
                $arm->subjects()->syncWithoutDetaching([
                    $subjectId => [
                        'id' => Str::uuid()->toString(),
                        'is_compulsory' => true,
                    ],
                ]);
            });
        }

        return ApiResponse::success([
            'is_compulsory' => $newStatus,
        ], 'Subject compulsory status updated.');
    }

    public function assignTeacher(Request $request, string $id): JsonResponse
    {

        $level = ClassLevel::findOrFail($id);

        $validated = $request->validate([
            'teacher_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $teacher = User::role('teacher')->findOrFail($validated['teacher_id']);

        $teacher->teacherProfile()->update(
            ['class_level_id' => $level->id]
        );

        return ApiResponse::success(
            $level->load(['classArms.assignedTeacher', 'subjects']),
            "Teacher assigned to class level {$level->name} successfully."
        );
    }
}
