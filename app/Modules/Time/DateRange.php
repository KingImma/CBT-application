<?php

declare(strict_types=1);

namespace App\Modules\Time;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Immutable time range shared across domains.
 *
 * The strict {@see self::overlaps()} check is half-open: a range that ends
 * exactly when another starts does not collide, which is what back-to-back
 * exam slots need. {@see self::overlapsInclusive()} is provided for calendars
 * where a shared endpoint is a conflict (academic sessions, for example).
 *
 * Construction fails loudly instead of silently producing a backwards range;
 * callers that treat "not configured" as a valid state use
 * {@see self::tryOf()} and handle the null explicitly.
 */
final readonly class DateRange
{
    private function __construct(
        public CarbonInterface $start,
        public CarbonInterface $end,
    ) {}

    public static function of(CarbonInterface $start, CarbonInterface $end): self
    {
        if (! $start->lessThan($end)) {
            throw new InvalidArgumentException(sprintf(
                'A date range requires start to precede end; got [%s, %s].',
                $start->toIso8601String(),
                $end->toIso8601String(),
            ));
        }

        return new self($start, $end);
    }

    /**
     * Null-safe construction for optional bounds: returns null when either
     * bound is missing or the range is not ordered.
     */
    public static function tryOf(?CarbonInterface $start, ?CarbonInterface $end): ?self
    {
        if ($start === null || $end === null || ! $start->lessThan($end)) {
            return null;
        }

        return new self($start, $end);
    }

    public function contains(CarbonInterface $moment): bool
    {
        return $moment->greaterThanOrEqualTo($this->start)
            && $moment->lessThanOrEqualTo($this->end);
    }

    public function containsRange(self $other): bool
    {
        return $other->start->greaterThanOrEqualTo($this->start)
            && $other->end->lessThanOrEqualTo($this->end);
    }

    /** Ranges that merely touch at an endpoint do not overlap. */
    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end)
            && $this->end->greaterThan($other->start);
    }

    /** A shared endpoint counts as an overlap. */
    public function overlapsInclusive(self $other): bool
    {
        return $this->start->lessThanOrEqualTo($other->end)
            && $this->end->greaterThanOrEqualTo($other->start);
    }

    public function durationInMinutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end, true);
    }
}
