<?php

declare(strict_types=1);

namespace App\Domains\Exams\Support;

use App\Models\Tenant\Exam;
use App\Models\Tenant\SchoolSetting;

/**
 * Single source of truth for the pass mark used by result reports.
 *
 * Completed exams normalise to a non-null pass mark, but legacy/imported
 * rows can have it null. Falling back to the tenant default keeps pass/fail
 * reporting consistent between the summary and the per-student rows.
 */
final class ResolveExamPassMark
{
    private const FALLBACK_PASS_MARK = 50.0;

    public function execute(Exam $exam): float
    {
        if ($exam->pass_mark !== null) {
            return (float) $exam->pass_mark;
        }

        $default = SchoolSetting::value('default_pass_mark');

        return $default !== null ? (float) $default : self::FALLBACK_PASS_MARK;
    }
}
