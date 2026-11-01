<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

final class ScheduleTest extends TestCase
{
    public function test_nothing_runs_more_often_than_daily_so_the_environment_can_sleep(): void
    {
        $events = $this->app->make(Schedule::class)->events();

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            [$minute, $hour, $day, $month, $weekday] = explode(' ', $event->expression);

            $this->assertTrue(
                is_numeric($minute) && is_numeric($hour) && $day === '*' && $month === '*' && $weekday === '*',
                $event->command.' runs at '.$event->expression,
            );
        }
    }

    public function test_daily_work_runs_at_noon_and_task_reminders_at_seven_in_the_evening_in_slovakia(): void
    {
        foreach ($this->app->make(Schedule::class)->events() as $event) {
            $this->assertSame('Europe/Bratislava', $event->timezone, (string) $event->command);
            $this->assertSame($this->expectedExpression($event), $event->expression, (string) $event->command);
        }
    }

    private function expectedExpression(Event $event): string
    {
        return str_contains((string) $event->command, 'app:remind-tasks') ? '0 19 * * *' : '0 12 * * *';
    }
}
