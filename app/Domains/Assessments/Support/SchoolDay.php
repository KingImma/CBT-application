<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The school's calendar day, derived from a UTC instant.
 *
 * Slots and deadlines are stored in UTC, but "how many subjects does this class
 * already have on Tuesday" is a wall-clock question in the school's timezone.
 * A 00:30 Lagos slot sits at 23:30Z the previous day, so counting UTC dates
 * would file it under the wrong day — and disagree with the frontend, which
 * keys daily_usage by the Lagos date the teacher picked.
 *
 * This is the single definition used by both the daily-cap rule and the
 * daily_usage meta so they can never drift apart.
 */
final class SchoolDay
{
    public static function timezone(): string
    {
        return (string) config('school.timezone', 'Africa/Lagos');
    }

    /** The start of the school-timezone day an instant falls in. */
    public static function of(CarbonInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)
            ->setTimezone(self::timezone())
            ->startOfDay();
    }

    /**
     * UTC bounds of a school day: [$startUtc, $endUtc), usable as a range
     * query against a UTC datetime column.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function bounds(CarbonImmutable|CarbonInterface $dayStart): array
    {
        $start = self::of($dayStart);

        return [$start->utc(), $start->addDay()->utc()];
    }
}