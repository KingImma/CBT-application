<?php

declare(strict_types=1);

namespace App\Shared\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Optional;
use Throwable;

/**
 * Normalises an incoming date-time to UTC before it can be persisted.
 *
 * Eloquent does not convert timezones when it writes a datetime column:
 * HasAttributes::fromDateTime() formats the Carbon instance in whatever zone it
 * was parsed in and throws the offset away. A client correctly sending
 * "2026-09-08T09:25:00+01:00" (the instant 08:25Z) would therefore be stored as
 * 09:25 in a column that every deadline check reads as UTC
 * (TickAssessments, SubmissionPolicy, StartExamAttempt) —
 * one hour late for real, whatever the screens show.
 *
 * Values that carry an offset or a "Z" are converted to that instant in UTC.
 * Values with no offset are treated as UTC, which is what Carbon::parse()
 * already did for these DTOs, so no existing caller changes meaning.
 *
 * The wire contract stays "ISO 8601 with an explicit offset"; this cast only
 * guarantees the storage end of it.
 */
final class ToUtc implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if ($value === null || $value === '' || $value instanceof Optional) {
            return $value;
        }

        $instant = self::normalize($value);

        // Leave unreadable values untouched so the #[Date] rule reports them.
        return $instant?->toIso8601String() ?? $value;
    }

    /** The UTC instant behind any date-ish value, or null when it cannot be read. */
    public static function normalize(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '' || $value instanceof Optional) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        if (! is_string($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}