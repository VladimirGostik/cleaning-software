<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\TaskReminderSlotEnum;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskReminderSlotEnumTest extends TestCase
{
    public function test_hours_follow_case_order(): void
    {
        $this->assertSame(
            [7, 12, 19],
            array_map(static fn (TaskReminderSlotEnum $slot): int => $slot->hour(), TaskReminderSlotEnum::cases()),
        );
        $this->assertSame('Europe/Bratislava', TaskReminderSlotEnum::TIMEZONE);
    }

    /** @return array<string, array{string, string}> */
    public static function rounding(): array
    {
        return [
            'before morning rounds up to morning' => ['2027-01-15 05:59:59', '2027-01-15 06:00:00'],
            'exactly on morning stays' => ['2027-01-15 06:00:00', '2027-01-15 06:00:00'],
            'seconds past the minute are dropped first' => ['2027-01-15 06:00:30', '2027-01-15 06:00:00'],
            'one minute past morning goes to noon' => ['2027-01-15 06:01:00', '2027-01-15 11:00:00'],
            'ten local goes to noon' => ['2027-01-15 09:00:00', '2027-01-15 11:00:00'],
            'exactly on noon stays' => ['2027-01-15 11:00:00', '2027-01-15 11:00:00'],
            'afternoon goes to evening' => ['2027-01-15 13:00:00', '2027-01-15 18:00:00'],
            'after evening goes to next morning' => ['2027-01-15 18:30:00', '2027-01-16 06:00:00'],
            'summer offset' => ['2027-07-15 06:30:00', '2027-07-15 10:00:00'],
            'spring forward day morning' => ['2027-03-27 20:00:00', '2027-03-28 05:00:00'],
            'autumn back day morning' => ['2027-10-30 18:30:00', '2027-10-31 06:00:00'],
        ];
    }

    #[DataProvider('rounding')]
    public function test_next_at_or_after_rounds_up_to_the_slot_in_utc(string $input, string $expected): void
    {
        $result = TaskReminderSlotEnum::nextAtOrAfter(CarbonImmutable::parse($input, 'UTC'));

        $this->assertSame($expected, $result->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $result->getTimezone()->getName());
    }

    public function test_input_in_another_timezone_is_compared_by_instant(): void
    {
        $result = TaskReminderSlotEnum::nextAtOrAfter(CarbonImmutable::parse('2027-01-15 10:00:00', 'Europe/Bratislava'));

        $this->assertSame('2027-01-15 11:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_instant_on_a_date_uses_the_local_hour_with_the_right_offset(): void
    {
        $this->assertSame('2027-01-15 06:00:00', TaskReminderSlotEnum::Morning->instantOn('2027-01-15')->format('Y-m-d H:i:s'));
        $this->assertSame('2027-07-15 17:00:00', TaskReminderSlotEnum::Evening->instantOn('2027-07-15')->format('Y-m-d H:i:s'));
        $this->assertSame('2027-03-28 05:00:00', TaskReminderSlotEnum::Morning->instantOn('2027-03-28')->format('Y-m-d H:i:s'));
        $this->assertSame('2027-10-31 06:00:00', TaskReminderSlotEnum::Morning->instantOn('2027-10-31')->format('Y-m-d H:i:s'));
    }

    public function test_date_and_slot_for_an_instant(): void
    {
        [$date, $slot] = TaskReminderSlotEnum::dateAndSlotFor(CarbonImmutable::parse('2027-01-15 09:00:00', 'UTC'));
        $this->assertSame('2027-01-15', $date);
        $this->assertSame(TaskReminderSlotEnum::Noon, $slot);

        [$date, $slot] = TaskReminderSlotEnum::dateAndSlotFor(CarbonImmutable::parse('2027-01-15 18:30:00', 'UTC'));
        $this->assertSame('2027-01-16', $date);
        $this->assertSame(TaskReminderSlotEnum::Morning, $slot);
    }

    /** @return array<string, array{string, string, TaskReminderSlotEnum}> */
    public static function strictlyAfter(): array
    {
        return [
            'one minute before morning' => ['2027-01-15 05:59:00', '2027-01-15', TaskReminderSlotEnum::Morning],
            'exactly morning gives noon' => ['2027-01-15 06:00:00', '2027-01-15', TaskReminderSlotEnum::Noon],
            'evening half past gives next morning' => ['2027-01-15 18:30:00', '2027-01-16', TaskReminderSlotEnum::Morning],
        ];
    }

    #[DataProvider('strictlyAfter')]
    public function test_first_slot_strictly_after_now(string $now, string $date, TaskReminderSlotEnum $slot): void
    {
        $this->assertSame(
            [$date, $slot],
            TaskReminderSlotEnum::firstAfter(CarbonImmutable::parse($now, 'UTC')),
        );
    }
}
