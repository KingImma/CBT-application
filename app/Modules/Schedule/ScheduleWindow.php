<?php

declare(strict_types=1);

namespace App\Modules\Schedule;

use Carbon\CarbonInterface;

/**
 * A time window during which an action is permitted.
 *
 * Bounds are optional so the same value object can describe every window the
 * platform uses:
 *
 *  - fully bounded  → assessment student window (start + end)
 *  - deadline only  → question-submission deadline (end, no start)
 *  - start only     → open-ended window (start, no end)
 *
 * A window counts as "configured" only when both bounds exist and are
 * ordered; an unconfigured window never reports itself open. All predicates
 * take the current instant as an argument so the type stays pure and testable
 * rather than reaching for `now()` internally.
 */
final readonly class ScheduleWindow
{
    private function __construct(
        private ?CarbonInterface $start,
        private ?CarbonInterface $end,
    ) {}

    public static function of(?CarbonInterface $start, ?CarbonInterface $end): self
    {
        return new self($start, $end);
    }

    public function start(): ?CarbonInterface
    {
        return $this->start;
    }

    public function end(): ?CarbonInterface
    {
        return $this->end;
    }

    /** Both bounds present and ordered. */
    public function isSet(): bool
    {
        return $this->start !== null
            && $this->end !== null
            && $this->start->lessThan($this->end);
    }

    public function hasOpened(CarbonInterface $at): bool
    {
        return $this->start !== null && $at->greaterThanOrEqualTo($this->start);
    }

    /** The window is closed at the instant its end is reached, not after it. */
    public function hasClosed(CarbonInterface $at): bool
    {
        return $this->end !== null && $at->greaterThanOrEqualTo($this->end);
    }

    public function isOpenAt(CarbonInterface $at): bool
    {
        if ($this->start === null && $this->end === null) {
            return false;
        }

        return ! $this->hasClosed($at)
            && ($this->start === null || $this->hasOpened($at));
    }
}
