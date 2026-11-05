<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\RecurringBilledMonthEnum;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RecurringBilledMonthEnumTest extends TestCase
{
    /** @return array<string, array{RecurringBilledMonthEnum, string, ?string}> */
    public static function billedMonths(): array
    {
        return [
            'none' => [RecurringBilledMonthEnum::None, '2026-12-01', null],
            'current' => [RecurringBilledMonthEnum::Current, '2026-12-15', '2026-12-01'],
            'previous' => [RecurringBilledMonthEnum::Previous, '2026-12-01', '2026-11-01'],
            'previous across year' => [RecurringBilledMonthEnum::Previous, '2027-01-01', '2026-12-01'],
            'previous into short february' => [RecurringBilledMonthEnum::Previous, '2027-03-31', '2027-02-01'],
        ];
    }

    #[DataProvider('billedMonths')]
    public function test_billed_month_is_derived_from_the_run_date(RecurringBilledMonthEnum $rule, string $runDate, ?string $expected): void
    {
        $this->assertSame($expected, $rule->billedMonth(CarbonImmutable::parse($runDate))?->toDateString());
    }

    public function test_run_date_is_the_first_of_the_following_month_for_previous(): void
    {
        $billed = CarbonImmutable::parse('2026-12-01');

        $this->assertSame('2027-01-01', RecurringBilledMonthEnum::Previous->runDate($billed, 1)->toDateString());
        $this->assertSame('2026-12-10', RecurringBilledMonthEnum::Current->runDate($billed, 10)->toDateString());
    }

    public function test_run_date_clamps_the_day_to_the_month_length(): void
    {
        $this->assertSame('2027-02-28', RecurringBilledMonthEnum::Current->runDate(CarbonImmutable::parse('2027-02-01'), 31)->toDateString());
    }

    public function test_month_is_formatted_without_a_leading_zero(): void
    {
        $this->assertSame('9/2026', RecurringBilledMonthEnum::formatMonth(CarbonImmutable::parse('2026-09-01')));
        $this->assertSame('{mesiac/rok}', RecurringBilledMonthEnum::TOKEN);
    }
}
