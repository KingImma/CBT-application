<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Time;

use App\Modules\Time\DateRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DateRangeTest extends TestCase
{
    #[Test]
    public function it_builds_a_valid_range(): void
    {
        $range = DateRange::of(
            CarbonImmutable::parse('2026-01-01 09:00'),
            CarbonImmutable::parse('2026-01-01 10:00'),
        );

        $this->assertSame(60, $range->durationInMinutes());
    }

    #[Test]
    public function it_throws_when_start_does_not_precede_end(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DateRange::of(
            CarbonImmutable::parse('2026-01-01 10:00'),
            CarbonImmutable::parse('2026-01-01 10:00'),
        );
    }

    #[Test]
    public function try_of_returns_null_for_incomplete_or_reversed_bounds(): void
    {
        $start = CarbonImmutable::parse('2026-01-01 09:00');
        $end = CarbonImmutable::parse('2026-01-01 10:00');

        $this->assertNull(DateRange::tryOf(null, $end));
        $this->assertNull(DateRange::tryOf($start, null));
        $this->assertNull(DateRange::tryOf($start, $start));
        $this->assertInstanceOf(DateRange::class, DateRange::tryOf($start, $end));
    }

    #[Test]
    public function it_contains_moments_inclusively(): void
    {
        $range = DateRange::of(
            CarbonImmutable::parse('2026-01-01 09:00'),
            CarbonImmutable::parse('2026-01-01 10:00'),
        );

        $this->assertTrue($range->contains(CarbonImmutable::parse('2026-01-01 09:00')));
        $this->assertTrue($range->contains(CarbonImmutable::parse('2026-01-01 09:30')));
        $this->assertTrue($range->contains(CarbonImmutable::parse('2026-01-01 10:00')));
        $this->assertFalse($range->contains(CarbonImmutable::parse('2026-01-01 10:01')));
    }

    #[Test]
    public function it_only_contains_ranges_fully_inside_it(): void
    {
        $outer = DateRange::of(
            CarbonImmutable::parse('2026-01-01 09:00'),
            CarbonImmutable::parse('2026-01-01 12:00'),
        );

        $inner = DateRange::of(
            CarbonImmutable::parse('2026-01-01 09:30'),
            CarbonImmutable::parse('2026-01-01 10:30'),
        );

        $overhanging = DateRange::of(
            CarbonImmutable::parse('2026-01-01 11:30'),
            CarbonImmutable::parse('2026-01-01 12:30'),
        );

        $this->assertTrue($outer->containsRange($inner));
        $this->assertFalse($outer->containsRange($overhanging));
    }

    #[Test]
    public function strict_overlap_ignores_ranges_that_only_touch(): void
    {
        $first = DateRange::of(
            CarbonImmutable::parse('2026-01-01 09:00'),
            CarbonImmutable::parse('2026-01-01 10:00'),
        );

        $touching = DateRange::of(
            CarbonImmutable::parse('2026-01-01 10:00'),
            CarbonImmutable::parse('2026-01-01 11:00'),
        );

        $overlapping = DateRange::of(
            CarbonImmutable::parse('2026-01-01 09:59'),
            CarbonImmutable::parse('2026-01-01 10:30'),
        );

        $this->assertFalse($first->overlaps($touching));
        $this->assertTrue($first->overlaps($overlapping));
    }

    #[Test]
    public function inclusive_overlap_counts_a_shared_endpoint(): void
    {
        $first = DateRange::of(
            CarbonImmutable::parse('2026-01-01 09:00'),
            CarbonImmutable::parse('2026-01-01 10:00'),
        );

        $touching = DateRange::of(
            CarbonImmutable::parse('2026-01-01 10:00'),
            CarbonImmutable::parse('2026-01-01 11:00'),
        );

        $this->assertTrue($first->overlapsInclusive($touching));
    }
}
