<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Topic;
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
            ->with([
                'classLevels' => fn ($q) => $q->where('class_level_id', $classLevel->id)
                    ->select('class_levels.id', 'class_levels.name'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($subject) use ($classLevel) {
                $pivot = $subject->classLevels->first()?->pivot;

                $topics = Topic::select('id', 'name', 'subject_id', 'class_level_id', 'parent_id', 'order')
                    ->where('subject_id', $subject->id)
                    ->where('class_level_id', $classLevel->id)
                    ->whereNull('parent_id')
                    ->with(['subject:id,name', 'classLevel:id,name'])
                    ->with(['children' => fn ($q) => $q->withCount('questions')->select('id', 'name', 'subject_id', 'class_level_id', 'parent_id', 'order')])
                    ->withCount('questions')
                    ->orderBy('order')
                    ->orderBy('name')
                    ->get();

                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'is_compulsory' => (bool) ($pivot?->is_compulsory ?? false),
                    'topics' => $topics,
                ];
            });

        return ApiResponse::success([
            'class_level' => $classLevel,
            'subjects' => $subjects,
        ], 'Question bank form data retrieved.');
    }
}
