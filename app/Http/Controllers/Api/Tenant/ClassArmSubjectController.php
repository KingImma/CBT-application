<?php

// - Manages per-arm subject allocation
// - index: shows arm's subjects + what's available from its class level
// - sync: bulk-replace the arm's entire subject list in one operation
// - attach: add one subject to an arm
// - detach: remove one subject from an arm
// - Expected: school admin configures curriculum per arm after arms are created
// - Alternative: subjects array on arm update — less explicit, harder to query

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Subject;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @group Classes & Arms
 * * APIs for managing class structures (e.g., JSS1) and specific arms (e.g., JSS1A), including subject mapping.
 */
class ClassArmSubjectController extends Controller
{
    /**
     * List subjects assigned to a specific arm.
     * Also returns unassigned subjects from the parent class level
     * so the frontend can render the "add subject" options without extra calls.
     *
     * @subgroup Subject Mapping
     *
     * @urlParam classLevelId string required The class level UUID.
     * @urlParam armId string required The arm UUID.
     */
    public function index(string $classLevelId, string $armId): JsonResponse
    {
        $arm = ClassArm::where('class_level_id', $classLevelId)
            ->with([
                'subjects' => fn ($q) => $q->with(['teacherAssignments' => fn ($q2) => $q2
                    ->where('class_level_id', $classLevelId)
                    ->with('user:id,first_name,last_name'),
                ]),
                'classLevel.subjects',
            ])
            ->findOrFail($armId);

        $assignedIds = $arm->subjects->pluck('id');
        $levelSubjects = $arm->classLevel->subjects;
        $unassigned = $levelSubjects->whereNotIn('id', $assignedIds)->values();

        return ApiResponse::success([
            'arm' => [
                'id' => $arm->id,
                'name' => $arm->name,
                'class_level' => $arm->classLevel->name,
            ],
            'assigned' => $arm->subjects->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'is_compulsory' => (bool) $s->pivot->is_compulsory,
                'assigned_teacher' => ($teacher = $s->teacherAssignments->first()?->user) ? [
                    'id' => $teacher->id,
                    'first_name' => $teacher->first_name,
                    'last_name' => $teacher->last_name,
                ] : null,
            ]),
            'unassigned' => $unassigned->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
            ]),
            'total_assigned' => $arm->subjects->count(),
            'total_unassigned' => $unassigned->count(),
        ], 'Arm subjects retrieved successfully.');
    }

    /**
     * Sync — replace the arm's entire subject list atomically.
     * Best for a "save curriculum" UI where the admin sets all subjects at once.
     * Subjects not in the list are detached, new ones are attached.
     *
     * @subgroup Subject Mapping
     *
     * @urlParam classLevelId string required The class level UUID.
     * @urlParam armId string required The arm UUID.
     *
     * @bodyParam subject_ids array required Array of subject UUIDs. No-example
     * @bodyParam subject_ids.* string Subject UUID. No-example
     * @bodyParam compulsory_ids array Array of compulsory subject UUIDs. No-example
     * @bodyParam compulsory_ids.* string Subject UUID. No-example
     */
    public function sync(Request $request, string $classLevelId, string $armId): JsonResponse
    {
        $arm = ClassArm::where('class_level_id', $classLevelId)->findOrFail($armId);

        $validated = $request->validate([
            'subject_ids' => ['required', 'array'],
            'subject_ids.*' => ['uuid', 'exists:subjects,id'],
            'compulsory_ids' => ['nullable', 'array'],
            'compulsory_ids.*' => ['uuid'],
        ]);

        // Verify all subjects belong to this class level
        $levelSubjectIds = ClassLevel::findOrFail($classLevelId)
            ->subjects()
            ->pluck('subjects.id')
            ->toArray();

        $invalid = array_diff($validated['subject_ids'], $levelSubjectIds);
        if (! empty($invalid)) {
            return ApiResponse::error(
                'Some subjects are not assigned to this class level.',
                422,
                meta: [
                    'invalid_subject_ids' => array_values($invalid),
                ]
            );
        }

        // Build sync data — each entry carries its own UUID and compulsory flag
        $syncData = [];
        foreach ($validated['subject_ids'] as $subjectId) {
            $syncData[$subjectId] = [
                'id' => Str::uuid()->toString(),
                'is_compulsory' => in_array($subjectId, $validated['compulsory_ids'] ?? []),
            ];
        }

        $arm->subjects()->sync($syncData);

        return ApiResponse::success([
            'arm' => $arm->load('subjects'),
        ], "Subjects synced for {$arm->classLevel->name} {$arm->name}.");
    }

    /**
     * Attach a single subject to an arm.
     * Best for "add one subject" UI interactions.
     *
     * @subgroup Subject Mapping
     *
     * @urlParam classLevelId string required The class level UUID.
     * @urlParam armId string required The arm UUID.
     *
     * @bodyParam subject_id string required The subject UUID. No-example
     * @bodyParam is_compulsory boolean Whether the subject is compulsory. No-example
     */
    public function attach(Request $request, string $classLevelId, string $armId): JsonResponse
    {
        $arm = ClassArm::where('class_level_id', $classLevelId)->findOrFail($armId);

        $validated = $request->validate([
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'is_compulsory' => ['nullable', 'boolean'],
        ]);

        // Verify subject belongs to this class level
        $belongsToLevel = ClassLevel::findOrFail($classLevelId)
            ->subjects()
            ->where('subjects.id', $validated['subject_id'])
            ->exists();

        if (! $belongsToLevel) {
            return ApiResponse::error('This subject is not assigned to the class level. Assign it to the level first.', 422);
        }

        // Check if already attached to this arm
        if ($arm->subjects()->where('subjects.id', $validated['subject_id'])->exists()) {
            return ApiResponse::error('This subject is already assigned to this arm.', 422);
        }

        $arm->subjects()->attach($validated['subject_id'], [
            'id' => Str::uuid()->toString(),
            'is_compulsory' => $validated['is_compulsory'] ?? false,
        ]);

        $subject = Subject::find($validated['subject_id']);

        return ApiResponse::created([
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'is_compulsory' => $validated['is_compulsory'] ?? false,
            ],
        ], "'{$subject->name}' added to {$arm->classLevel->name} {$arm->name}.");
    }

    /**
     * Detach a subject from an arm.
     *
     * @subgroup Subject Mapping
     *
     * @urlParam classLevelId string required The class level UUID.
     * @urlParam armId string required The arm UUID.
     * @urlParam subjectId string required The subject UUID.
     */
    public function detach(string $classLevelId, string $armId, string $subjectId): JsonResponse
    {
        $arm = ClassArm::where('class_level_id', $classLevelId)->findOrFail($armId);

        if (! $arm->subjects()->where('subjects.id', $subjectId)->exists()) {
            return ApiResponse::error('This subject is not assigned to this arm.', 422);
        }

        $subject = Subject::findOrFail($subjectId);
        $arm->subjects()->detach($subjectId);

        return ApiResponse::message("'{$subject->name}' removed from {$arm->classLevel->name} {$arm->name}.");
    }

    /**
     * Toggle is_compulsory for a subject within an arm.
     *
     * @subgroup Subject Mapping
     *
     * @urlParam classLevelId string required The class level UUID.
     * @urlParam armId string required The arm UUID.
     * @urlParam subjectId string required The subject UUID.
     */
    public function toggleCompulsory(string $classLevelId, string $armId, string $subjectId): JsonResponse
    {
        $arm = ClassArm::where('class_level_id', $classLevelId)->findOrFail($armId);

        $subjectPivot = $arm->subjects()
            ->where('subjects.id', $subjectId)
            ->first();

        if (! $subjectPivot) {
            return ApiResponse::error('This subject is not assigned to this arm.', 404);
        }

        $current = (bool) $subjectPivot->pivot->is_compulsory;
        $arm->subjects()->updateExistingPivot($subjectId, [
            'is_compulsory' => ! $current,
        ]);

        return ApiResponse::success([
            'is_compulsory' => ! $current,
        ], ! $current
            ? 'Subject marked as compulsory.'
            : 'Subject marked as optional.');
    }

    /**
     * Copy the class level's full subject list to an arm.
     * Shortcut for "inherit everything from the level" — useful when
     * the arm only differs from the level in one or two subjects.
     *
     * @subgroup Subject Mapping
     *
     * @urlParam classLevelId string required The class level UUID.
     * @urlParam armId string required The arm UUID.
     */
    public function inheritFromLevel(string $classLevelId, string $armId): JsonResponse
    {
        $arm = ClassArm::where('class_level_id', $classLevelId)->findOrFail($armId);
        $levelSubjects = ClassLevel::findOrFail($classLevelId)
            ->subjects()
            ->withPivot('is_compulsory')
            ->get();

        $syncData = [];
        foreach ($levelSubjects as $subject) {
            $syncData[$subject->id] = [
                'id' => Str::uuid()->toString(),
                'is_compulsory' => (bool) $subject->pivot->is_compulsory,
            ];
        }

        $arm->subjects()->sync($syncData);

        return ApiResponse::success([
            'subjects_count' => $levelSubjects->count(),
        ], "All {$levelSubjects->count()} class level subjects copied to {$arm->name}.");
    }
}
