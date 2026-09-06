<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\TaskFrequencyEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TaskFrequencyEnumTest extends TestCase
{
    /** @return array<string, array{TaskFrequencyEnum, int|null}> */
    public static function intervalProvider(): array
    {
        return [
            'one_time' => [TaskFrequencyEnum::OneTime, null],
            'weekly_1x' => [TaskFrequencyEnum::Weekly1x, 7],
            'weekly_2x' => [TaskFrequencyEnum::Weekly2x, 3],
            'weekly_3x' => [TaskFrequencyEnum::Weekly3x, 2],
            'biweekly' => [TaskFrequencyEnum::Biweekly, 14],
            'monthly' => [TaskFrequencyEnum::Monthly, 30],
            'bimonthly' => [TaskFrequencyEnum::Bimonthly, 60],
            'seasonal' => [TaskFrequencyEnum::Seasonal, 90],
        ];
    }

    #[DataProvider('intervalProvider')]
    public function test_interval_days(TaskFrequencyEnum $frequency, ?int $expected): void
    {
        $this->assertSame($expected, $frequency->intervalDays());
    }

    public function test_one_time_is_not_recurring(): void
    {
        $this->assertFalse(TaskFrequencyEnum::OneTime->isRecurring());
    }

    public function test_weekly_is_recurring(): void
    {
        $this->assertTrue(TaskFrequencyEnum::Weekly1x->isRecurring());
    }
}
