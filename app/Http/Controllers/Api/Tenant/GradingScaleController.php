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
    /**
     * List all grading scales.
     *
     * @subgroup Grading Scales
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);

        return ApiResponse::success(
            GradingScaleData::collect(GradingScale::orderByDesc('is_default')->paginate($perPage)),
            'Grading scales retrieved successfully.'
        );
    }

    /**
     * Create a new grading scale with grade ranges.
     *
     * @subgroup Grading Scales
     *
     * @bodyParam name string required Scale name. Example: "Standard A-F"
     * @bodyParam is_default boolean Set as the default scale. No-example
     * @bodyParam grades array required Array of grade definitions (min 1). No-example
     * @bodyParam grades.*.label string required Grade label. Example: "A"
     * @bodyParam grades.*.min_score numeric required Minimum score (0-100). Example: 70
     * @bodyParam grades.*.max_score numeric required Maximum score (0-100). Example: 100
     * @bodyParam grades.*.remark string nullable Remark for this grade. Example: "Excellent"
     */
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

    /**
     * Get a single grading scale.
     *
     * @subgroup Grading Scales
     *
     * @urlParam id string required The grading scale UUID.
     */
    public function show(string $id): JsonResponse
    {
        $scale = GradingScale::findOrFail($id);

        return ApiResponse::success(GradingScaleData::from($scale), 'Grading scale retrieved successfully.');
    }

    /**
     * Update a grading scale.
     *
     * @subgroup Grading Scales
     *
     * @urlParam id string required The grading scale UUID.
     *
     * @bodyParam name string Scale name. No-example
     * @bodyParam is_default boolean Set as default. No-example
     * @bodyParam grades array Grade definitions. No-example
     * @bodyParam grades.*.label string Grade label. No-example
     * @bodyParam grades.*.min_score numeric Minimum score. No-example
     * @bodyParam grades.*.max_score numeric Maximum score. No-example
     * @bodyParam grades.*.remark string nullable Remark. No-example
     */
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

    /**
     * Delete a grading scale.
     *
     * @subgroup Grading Scales
     *
     * @urlParam id string required The grading scale UUID.
     */
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
