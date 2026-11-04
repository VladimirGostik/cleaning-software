<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\TaskReminderSlotEnum;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ScheduleTest extends TestCase
{
    public function test_nothing_wakes_the_environment_more_often_than_the_two_daily_candidates(): void
    {
        $events = $this->app->make(Schedule::class)->events();

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertMatchesRegularExpression('/^0 \d+,\d+ \* \* \*$/', $event->expression, (string) $event->command);
        }
    }

    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function moments(): array
    {
        return [
            'winter morning slot' => ['2027-01-15 06:00:00', false, true],
            'winter hour before morning slot' => ['2027-01-15 05:00:00', false, false],
            'winter noon' => ['2027-01-15 11:00:00', true, true],
            'winter hour before noon' => ['2027-01-15 10:00:00', false, false],
            'winter evening' => ['2027-01-15 18:00:00', false, true],
            'winter hour before evening' => ['2027-01-15 17:00:00', false, false],
            'summer morning slot' => ['2027-07-15 05:00:00', false, true],
            'summer hour after morning slot' => ['2027-07-15 06:00:00', false, false],
            'summer noon' => ['2027-07-15 10:00:00', true, true],
            'summer hour after noon' => ['2027-07-15 11:00:00', false, false],
            'summer evening' => ['2027-07-15 17:00:00', false, true],
            'summer hour after evening' => ['2027-07-15 18:00:00', false, false],
        ];
    }

    #[DataProvider('moments')]
    public function test_daily_work_runs_at_noon_and_task_reminders_at_each_slot_in_slovakia_all_year(
        string $utc,
        bool $dailyWorkRuns,
        bool $remindersRun,
    ): void {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC'));

        $reminders = 0;

        foreach ($this->app->make(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, 'app:remind-tasks')) {
                $reminders += (int) $this->runsNow($event);

                continue;
            }

            $this->assertSame($dailyWorkRuns, $this->runsNow($event), $event->command.' at '.$utc.' UTC');
        }

        $this->assertSame($remindersRun ? 1 : 0, $reminders, 'reminder runs at '.$utc.' UTC');
    }

    public function test_task_reminders_are_registered_once_per_slot(): void
    {
        $reminders = array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn (Event $event): bool => str_contains((string) $event->command, 'app:remind-tasks'),
        );

        $this->assertCount(count(TaskReminderSlotEnum::cases()), $reminders);
    }

    private function runsNow(Event $event): bool
    {
        return $event->isDue($this->app) && $event->filtersPass($this->app);
    }
}
