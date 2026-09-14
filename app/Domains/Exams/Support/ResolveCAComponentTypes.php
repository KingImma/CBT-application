<?php

declare(strict_types=1);

namespace App\Domains\Exams\Support;

use App\Models\Tenant\SchoolSetting;
use Illuminate\Support\Facades\Cache;

final class ResolveCAComponentTypes
{
    private const SETTING_KEY = "ca_component_exam_types";

    private const DEFAULT_TYPES = ["ca", "quiz", "test"];

    /* @@return string[] */
    public function execute(): array
    {
        return Cache::remember(
            "school_settings:" . self::SETTING_KEY . ":" . (tenant("id") ?? "central"),
            now()->addHour(),
            function (): array {
                $raw = SchoolSetting::where('key', self::SETTING_KEY)->value('value');

                if ($raw === null) {
                    return self::DEFAULT_TYPES;
                }

                $decoded = json_decode($raw, true);

                return is_array($decoded) && $decoded !== [] ? $decoded : self::DEFAULT_TYPES;
            }
        );
    }
}
