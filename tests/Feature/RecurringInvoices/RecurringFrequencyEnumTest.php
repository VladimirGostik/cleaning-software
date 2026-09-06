<?php

declare(strict_types=1);

namespace Tests\Feature\RecurringInvoices;

use App\Enums\RecurringFrequencyEnum;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

final class RecurringFrequencyEnumTest extends TestCase
{
    public function test_months_interval_matches_each_frequency(): void
    {
        $this->assertSame(1, RecurringFrequencyEnum::Monthly->monthsInterval());
        $this->assertSame(2, RecurringFrequencyEnum::EveryTwoMonths->monthsInterval());
        $this->assertSame(3, RecurringFrequencyEnum::Quarterly->monthsInterval());
        $this->assertSame(6, RecurringFrequencyEnum::SemiAnnually->monthsInterval());
        $this->assertSame(12, RecurringFrequencyEnum::Annually->monthsInterval());
    }

    public function test_next_run_date_adds_months_interval(): void
    {
        $from = Carbon::createStrict(2026, 1, 15);

        $next = RecurringFrequencyEnum::Monthly->nextRunDate($from, 15);

        $this->assertSame('2026-02-15', $next->toDateString());
    }

    public function test_next_run_date_clamps_day_31_from_month_end_without_skipping_february(): void
    {
        // Regression: addMonths() from day 31 overflows past February (Jan 31 + 1 month
        // lands on Mar 3 unless anchored on day 1 first). day_of_month=31 must still land
        // inside February, not skip it.
        $from = Carbon::createStrict(2026, 1, 31);

        $next = RecurringFrequencyEnum::Monthly->nextRunDate($from, 31);

        $this->assertSame('2026-02-28', $next->toDateString());
    }

    public function test_next_run_date_clamps_day_30_from_month_end_without_skipping_february(): void
    {
        $from = Carbon::createStrict(2026, 1, 30);

        $next = RecurringFrequencyEnum::Monthly->nextRunDate($from, 30);

        $this->assertSame('2026-02-28', $next->toDateString());
    }

    public function test_next_run_date_clamps_day_to_end_of_short_month(): void
    {
        $from = Carbon::createStrict(2026, 1, 1);

        $next = RecurringFrequencyEnum::Monthly->nextRunDate($from, 30);

        // 2026 is not a leap year — February has 28 days.
        $this->assertSame('2026-02-28', $next->toDateString());
    }

    public function test_next_run_date_clamps_day_28_in_february_leap_year(): void
    {
        $from = Carbon::createStrict(2028, 1, 1);

        $next = RecurringFrequencyEnum::Monthly->nextRunDate($from, 31);

        // 2028 is a leap year — February has 29 days.
        $this->assertSame('2028-02-29', $next->toDateString());
    }

    public function test_next_run_date_returns_start_of_day(): void
    {
        $from = Carbon::createStrict(2026, 1, 15, 14, 30, 0);

        $next = RecurringFrequencyEnum::Monthly->nextRunDate($from, 15);

        $this->assertSame('00:00:00', $next->toTimeString());
    }

    public function test_quarterly_adds_three_months(): void
    {
        $from = Carbon::createStrict(2026, 1, 1);

        $next = RecurringFrequencyEnum::Quarterly->nextRunDate($from, 1);

        $this->assertSame('2026-04-01', $next->toDateString());
    }

    public function test_quarterly_from_month_end_does_not_skip_february(): void
    {
        $from = Carbon::createStrict(2026, 11, 30);

        $next = RecurringFrequencyEnum::Quarterly->nextRunDate($from, 30);

        $this->assertSame('2027-02-28', $next->toDateString());
    }

    public function test_annually_from_leap_day_clamps_to_february_28_next_year(): void
    {
        $from = Carbon::createStrict(2028, 2, 29);

        $next = RecurringFrequencyEnum::Annually->nextRunDate($from, 29);

        // 2029 is not a leap year — February has 28 days.
        $this->assertSame('2029-02-28', $next->toDateString());
    }

    public function test_twelve_consecutive_monthly_steps_from_month_end_never_skip_a_month(): void
    {
        $current = Carbon::createStrict(2026, 1, 31);
        $expected = [
            '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31',
            '2026-06-30', '2026-07-31', '2026-08-31', '2026-09-30',
            '2026-10-31', '2026-11-30', '2026-12-31', '2027-01-31',
        ];

        $actual = [];
        foreach ($expected as $ignored) {
            $current = RecurringFrequencyEnum::Monthly->nextRunDate($current, 31);
            $actual[] = $current->toDateString();
        }

        $this->assertSame($expected, $actual);
    }
}
