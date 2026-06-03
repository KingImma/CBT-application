<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Data\GradingScale\GradingScaleData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\GradingScale;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group School Configuration
 * * APIs for managing tenant-specific preferences and global grading scales.
 */
class GradingScaleController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            GradingScaleData::collection(GradingScale::orderByDesc('is_default')->get()),
            'Grading scales retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_default' => ['boolean'],
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.label' => ['required', 'string'],
            'grades.*.min_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'grades.*.max_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'grades.*.remark' => ['nullable', 'string'],
        ]);

        $error = $this->validateGradeRanges($validated['grades']);
        if ($error !== null) {
            return ApiResponse::error($error, 422);
        }

        if (! empty($validated['is_default'])) {
            GradingScale::where('is_default', true)->update(['is_default' => false]);
        }

        $scale = GradingScale::create($validated);

        return ApiResponse::created(GradingScaleData::from($scale), 'Grading scale created.');
    }

    public function show(string $id): JsonResponse
    {
        $scale = GradingScale::findOrFail($id);

        return ApiResponse::success(GradingScaleData::from($scale), 'Grading scale retrieved successfully.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $scale = GradingScale::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
            'grades' => ['sometimes', 'array', 'min:1'],
            'grades.*.label' => ['required_with:grades', 'string'],
            'grades.*.min_score' => ['required_with:grades', 'numeric', 'min:0', 'max:100'],
            'grades.*.max_score' => ['required_with:grades', 'numeric', 'min:0', 'max:100'],
            'grades.*.remark' => ['nullable', 'string'],
        ]);

        if (! empty($validated['grades'])) {
            $error = $this->validateGradeRanges($validated['grades']);
            if ($error !== null) {
                return ApiResponse::error($error, 422);
            }
        }

        if (! empty($validated['is_default'])) {
            GradingScale::where('is_default', true)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $scale->update($validated);

        return ApiResponse::success(GradingScaleData::from($scale->fresh()), 'Grading scale updated.');
    }

    public function destroy(string $id): JsonResponse
    {
        $scale = GradingScale::findOrFail($id);

        if ($scale->is_default) {
            return ApiResponse::error('Cannot delete the default grading scale. Set another as default first.', 422);
        }

        $scale->delete();

        return ApiResponse::message('Grading scale deleted.');
    }

    private function validateGradeRanges(array $grades): ?string
    {
        $labels = [];
        foreach ($grades as $g) {
            if ($g['min_score'] > $g['max_score']) {
                return "Grade {$g['label']}: minimum score ({$g['min_score']}) cannot exceed maximum score ({$g['max_score']}).";
            }

            if (in_array($g['label'], $labels)) {
                return "Duplicate grade label '{$g['label']}' found. Each label must be unique.";
            }
            $labels[] = $g['label'];
        }

        $sorted = collect($grades)->sortBy('min_score')->values();

        for ($i = 0; $i < $sorted->count() - 1; $i++) {
            $current = $sorted[$i];
            $next = $sorted[$i + 1];

            if ($current['max_score'] >= $next['min_score']) {
                return "Grade ranges overlap: '{$current['label']}' ({$current['min_score']}-{$current['max_score']}) and '{$next['label']}' ({$next['min_score']}-{$next['max_score']}).";
            }

            if (($next['min_score'] - $current['max_score']) > 1) {
                return "Gap between grade ranges: '{$current['label']}' ends at {$current['max_score']} but '{$next['label']}' starts at {$next['min_score']}.";
            }
        }

        return null;
    }
}
