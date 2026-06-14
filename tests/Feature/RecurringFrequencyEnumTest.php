<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RecurringFrequencyEnum;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class RecurringFrequencyEnumTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_monthly_next_run_from_jan_10_day_15_gives_feb_15(): void
    {
        $from = Carbon::parse('2026-01-10');
        $result = RecurringFrequencyEnum::Monthly->nextRunDate($from, 15);

        $this->assertSame('2026-02-15', $result->toDateString());
    }

    public function test_quarterly_adds_three_months(): void
    {
        $from = Carbon::parse('2026-01-10');
        $result = RecurringFrequencyEnum::Quarterly->nextRunDate($from, 10);

        $this->assertSame('2026-04-10', $result->toDateString());
    }

    public function test_annually_adds_twelve_months(): void
    {
        $from = Carbon::parse('2026-01-10');
        $result = RecurringFrequencyEnum::Annually->nextRunDate($from, 10);

        $this->assertSame('2027-01-10', $result->toDateString());
    }

    public function test_semi_annually_adds_six_months(): void
    {
        $from = Carbon::parse('2026-01-10');
        $result = RecurringFrequencyEnum::SemiAnnually->nextRunDate($from, 10);

        $this->assertSame('2026-07-10', $result->toDateString());
    }

    public function test_every_two_months_adds_two_months(): void
    {
        $from = Carbon::parse('2026-01-10');
        $result = RecurringFrequencyEnum::EveryTwoMonths->nextRunDate($from, 10);

        $this->assertSame('2026-03-10', $result->toDateString());
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function test_day_28_across_february_non_leap_clamps_to_28(): void
    {
        // 2026 is not a leap year; February has 28 days
        $from = Carbon::parse('2026-01-15');
        $result = RecurringFrequencyEnum::Monthly->nextRunDate($from, 28);

        $this->assertSame('2026-02-28', $result->toDateString());
    }

    public function test_result_is_start_of_day(): void
    {
        $from = Carbon::parse('2026-01-10 14:30:00');
        $result = RecurringFrequencyEnum::Monthly->nextRunDate($from, 15);

        $this->assertSame('00:00:00', $result->format('H:i:s'));
    }

    public function test_months_interval_values(): void
    {
        $this->assertSame(1, RecurringFrequencyEnum::Monthly->monthsInterval());
        $this->assertSame(2, RecurringFrequencyEnum::EveryTwoMonths->monthsInterval());
        $this->assertSame(3, RecurringFrequencyEnum::Quarterly->monthsInterval());
        $this->assertSame(6, RecurringFrequencyEnum::SemiAnnually->monthsInterval());
        $this->assertSame(12, RecurringFrequencyEnum::Annually->monthsInterval());
    }
}
