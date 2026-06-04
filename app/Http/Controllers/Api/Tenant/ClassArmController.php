<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Data\ClassArm\ClassArmData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @group Classes & Arms
 * * APIs for managing class structures (e.g., JSS1) and specific arms (e.g., JSS1A), including subject mapping.
 */
class ClassArmController extends Controller
{
    /**
     * List all arms for a class level.
     *
     * @subgroup Class Arms
     *
     * @urlParam classLevelId string required The class level UUID.
     */
    public function index(string $classLevelId): JsonResponse
    {
        $level = ClassLevel::findOrFail($classLevelId);

        return ApiResponse::success(
            ClassArmData::collect($level->classArms()->with('assignedTeacher')->withCount('students')->get()),
            'Class arms retrieved successfully.'
        );
    }

    /**
     * Create a new class arm.
     *
     * @subgroup Class Arms
     *
     * @urlParam classLevelId string required The class level UUID.
     *
     * @bodyParam name string required Arm name. Example: "A"
     */
    public function store(Request $request, string $classLevelId): JsonResponse
    {
        $level = ClassLevel::findOrFail($classLevelId);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('class_arms', 'name')
                    ->where('class_level_id', $level->id),
            ],
        ]);

        $arm = $level->classArms()->create($validated);

        $level->subjects()->wherePivot('is_compulsory', true)->each(function ($subject) use ($arm) {
            $arm->subjects()->syncWithoutDetaching([
                $subject->id => [
                    'id' => Str::uuid()->toString(),
                    'is_compulsory' => true,
                ],
            ]);
        });

        return ApiResponse::created(ClassArmData::from($arm), "Class arm '{$arm->name}' created.");
    }

    /**
     * Update a class arm name.
     *
     * @subgroup Class Arms
     *
     * @urlParam classLevelId string required The class level UUID.
     * @urlParam id string required The arm UUID.
     *
     * @bodyParam name string required Arm name. No-example
     */
    public function update(Request $request, string $classLevelId, string $id): JsonResponse
    {
        $arm = ClassArm::where('class_level_id', $classLevelId)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            Rule::unique('class_arms', 'name')
                ->where('class_level_id', $classLevelId)
                ->ignore($id),
        ]);

        $arm->update($validated);

        return ApiResponse::success(ClassArmData::from($arm->fresh()), 'Class arm updated.');
    }

    /**
     * Assign a teacher to a class arm.
     */
    public function assignTeacher(Request $request, string $classLevelId, string $id): JsonResponse
    {
        $validated = $request->validate([
            'assigned_teacher_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $arm = ClassArm::where('class_level_id', $classLevelId)->findOrFail($id);
        $arm->update(['assigned_teacher_id' => $validated['assigned_teacher_id']]);

        return ApiResponse::success(
            ClassArmData::from($arm->load('assignedTeacher')),
            'Teacher assigned to class.'
        );
    }

    /**
     * Delete a class arm (must have no students assigned).
     *
     * @subgroup Class Arms
     *
     * @urlParam classLevelId string required The class level UUID.
     * @urlParam id string required The arm UUID.
     */
    public function destroy(string $classLevelId, string $id): JsonResponse
    {
        $arm = ClassArm::where('class_level_id', $classLevelId)->findOrFail($id);

        if ($arm->students()->count() > 0) {
            return ApiResponse::error('Cannot delete a class arm that has stude
            
            nts assigned to it.', 422);
        }

        $arm->delete();

        return ApiResponse::message('Class arm deleted.');
    }
}
