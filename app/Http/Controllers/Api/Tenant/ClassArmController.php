<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassArmController extends Controller
{
    public function index(string $classLevelId): JsonResponse
    {
        $level = ClassLevel::findOrFail($classLevelId);

        return ApiResponse::success(
            $level->classArms()->withCount('students')->get(),
            'Class arms retrieved successfully.'
        );
    }

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

        return ApiResponse::created($arm, "Class arm '{$arm->name}' created.");
    }

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

        return ApiResponse::success($arm->fresh(), 'Class arm updated.');
    }

    public function destroy(string $classLevelId, string $id): JsonResponse
    {
        $arm = ClassArm::where('class_level_id', $classLevelId)->findOrFail($id);

        if ($arm->students()->count() > 0) {
            return ApiResponse::error('Cannot delete a class arm that has students assigned to it.', 422);
        }

        $arm->delete();

        return ApiResponse::message('Class arm deleted.');
    }
}
