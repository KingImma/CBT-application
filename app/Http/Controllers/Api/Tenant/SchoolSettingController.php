<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SchoolSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolSettingController extends Controller
{
    /**
     * Return all settings as a flat key-value map.
     * Frontend reads this once on dashboard load.
     */
    public function index(): JsonResponse
    {
        $settings = SchoolSetting::all()
            ->pluck('value', 'key');

        return response()->json($settings);
    }

    /**
     * Bulk update settings.
     * Accepts a key-value object — only updates keys that are sent.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings'   => ['required', 'array'],
            'settings.*' => ['nullable', 'string'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            SchoolSetting::updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        return response()->json([
            'message'  => 'Settings updated.',
            'settings' => SchoolSetting::all()->pluck('value', 'key'),
        ]);
    }
}