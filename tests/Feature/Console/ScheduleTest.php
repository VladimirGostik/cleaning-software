<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

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
            'winter noon' => ['2027-01-15 11:00:00', true, false],
            'winter hour before noon' => ['2027-01-15 10:00:00', false, false],
            'winter evening' => ['2027-01-15 18:00:00', false, true],
            'winter hour before evening' => ['2027-01-15 17:00:00', false, false],
            'summer noon' => ['2027-07-15 10:00:00', true, false],
            'summer hour after noon' => ['2027-07-15 11:00:00', false, false],
            'summer evening' => ['2027-07-15 17:00:00', false, true],
            'summer hour after evening' => ['2027-07-15 18:00:00', false, false],
        ];
    }

    #[DataProvider('moments')]
    public function test_daily_work_runs_at_noon_and_task_reminders_at_seven_in_the_evening_in_slovakia_all_year(
        string $utc,
        bool $dailyWorkRuns,
        bool $remindersRun,
    ): void {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC'));

        foreach ($this->app->make(Schedule::class)->events() as $event) {
            $expected = str_contains((string) $event->command, 'app:remind-tasks') ? $remindersRun : $dailyWorkRuns;

            $this->assertSame($expected, $this->runsNow($event), $event->command.' at '.$utc.' UTC');
        }
    }

    private function runsNow(Event $event): bool
    {
        return $event->isDue($this->app) && $event->filtersPass($this->app);
    }
}
