<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassArmController extends Controller
{
    public function index(string $classLevelId): JsonResponse
    {
        $level = ClassLevel::findOrFail($classLevelId);

        return response()->json(
            $level->classArms()->withCount('students')->get()
        );
    }

    public function store(Request $request, string $classLevelId): JsonResponse
    {
        $level = ClassLevel::findOrFail($classLevelId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $arm = $level->classArms()->create($validated);

        return response()->json($arm, 201);
    }

    public function update(Request $request, string $classLevelId, string $id): JsonResponse
    {
        $arm = ClassArm::where('class_level_id', $classLevelId)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $arm->update($validated);

        return response()->json($arm->fresh());
    }

    public function destroy(string $classLevelId, string $id): JsonResponse
    {
        $arm = ClassArm::where('class_level_id', $classLevelId)->findOrFail($id);

        if ($arm->students()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete a class arm that has students assigned to it.',
            ], 422);
        }

        $arm->delete();

        return response()->json(['message' => 'Class arm deleted.']);
    }
}