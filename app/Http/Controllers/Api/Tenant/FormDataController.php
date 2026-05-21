<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Subject;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormDataController extends Controller
{
    public function questionBankData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
        ]);

        $classLevel = ClassLevel::select('id', 'name')->findOrFail($validated['class_level_id']);

        $subjects = Subject::select('id', 'name', 'code')
            ->where('is_active', true)
            ->whereHas('classLevels', fn ($q) => $q->where('class_level_id', $classLevel->id))
            ->orderBy('name')
            ->get()
            ->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
            ]);

        return ApiResponse::success([
            'class_level' => $classLevel,
            'subjects' => $subjects,
        ], 'Question bank form data retrieved.');
    }
}
