<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SchoolSetting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
