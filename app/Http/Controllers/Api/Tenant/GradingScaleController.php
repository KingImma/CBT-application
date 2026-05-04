<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\GradingScale;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradingScaleController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            GradingScale::orderByDesc('is_default')->get(),
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

        if (! empty($validated['is_default'])) {
            GradingScale::where('is_default', true)->update(['is_default' => false]);
        }

        $scale = GradingScale::create($validated);

        return ApiResponse::created($scale, 'Grading scale created.');
    }

    public function show(string $id): JsonResponse
    {
        $scale = GradingScale::findOrFail($id);

        return ApiResponse::success($scale, 'Grading scale retrieved successfully.');
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

        if (! empty($validated['is_default'])) {
            GradingScale::where('is_default', true)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $scale->update($validated);

        return ApiResponse::success($scale->fresh(), 'Grading scale updated.');
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
}
