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

    public function test_next_run_date_clamps_day_to_end_of_short_month(): void
    {
        // Starting mid-January (not day 30/31) avoids Carbon's addMonths() overflow
        // past February entirely — the clamp itself is what's under test here.
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
}
