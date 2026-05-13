<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SchoolSetting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group School Configuration
 * * APIs for managing tenant-specific preferences and global grading scales.
 */
class SchoolSettingController extends Controller
{
    /**
     * Return all settings as a flat key-value map.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            SchoolSetting::all()->pluck('value', 'key'),
            'School settings retrieved successfully.'
        );
    }

    /**
     * Get a single setting by key.
     */
    public function show(string $key): JsonResponse
    {
        $setting = SchoolSetting::where('key', $key)->firstOrFail();

        return ApiResponse::success([
            'key' => $setting->key,
            'value' => $setting->value,
        ], 'School setting retrieved successfully.');
    }

    /**
     * Bulk update — accepts key-value object.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            SchoolSetting::updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        return ApiResponse::success(
            SchoolSetting::all()->pluck('value', 'key'),
            'Settings updated.'
        );
    }

    public function assessments(): JsonResponse
    {
        $settings = SchoolSetting::whereIn('key', [
            'assessment_max_score',
            'exam_max_score',
        ])->pluck('value', 'key');

        return ApiResponse::success([
            'assessment_max_score' => (int) ($settings['assessment_max_score'] ?? 50),
            'exam_max_score' => (int) ($settings['exam_max_score'] ?? 100),
        ], 'Assessment configuration retrieved.');
    }

    public function updateAssessments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assessment_max_score' => ['required', 'integer', 'min:1', 'max:100'],
            'exam_max_score' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        foreach ($validated as $key => $value) {
            SchoolSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => (string) $value,
                    'type' => 'integer',
                    'updated_at' => now(),
                ]
            );
        }

        return ApiResponse::success($validated, 'Assessment configuration updated.');
    }

    public function assessmentDefaults(): JsonResponse
    {
        return ApiResponse::success([
            'duration_minutes' => (int) (SchoolSetting::value('default_duration_minutes') ?? 120),
            'max_attempts' => (int) (SchoolSetting::value('default_max_attempts') ?? 1),
            'show_result_immediately' => filter_var(SchoolSetting::value('default_show_result_immediately') ?? false, FILTER_VALIDATE_BOOLEAN),
            'pass_mark' => (float) (SchoolSetting::value('default_pass_mark') ?? 50),
        ], 'Assessment defaults retrieved.');
    }

    public function updateAssessmentDefaults(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'max_attempts' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'show_result_immediately' => ['sometimes', 'boolean'],
            'pass_mark' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        foreach ($validated as $key => $value) {
            $dbKey = match ($key) {
                'duration_minutes' => 'default_duration_minutes',
                'max_attempts' => 'default_max_attempts',
                'show_result_immediately' => 'default_show_result_immediately',
                'pass_mark' => 'default_pass_mark',
            };

            SchoolSetting::updateOrCreate(
                ['key' => $dbKey],
                [
                    'value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
                    'type' => is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'decimal'),
                    'updated_at' => now(),
                ]
            );
        }

        return ApiResponse::success([
            'duration_minutes' => (int) (SchoolSetting::value('default_duration_minutes') ?? 120),
            'max_attempts' => (int) (SchoolSetting::value('default_max_attempts') ?? 1),
            'show_result_immediately' => filter_var(SchoolSetting::value('default_show_result_immediately') ?? false, FILTER_VALIDATE_BOOLEAN),
            'pass_mark' => (float) (SchoolSetting::value('default_pass_mark') ?? 50),
        ], 'Assessment defaults updated.');
    }
}
