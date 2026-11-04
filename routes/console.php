<?php

declare(strict_types=1);

use App\Console\Commands\CheckContractExpiry;
use App\Console\Commands\ExpirePendingActions;
use App\Console\Commands\ExpireQuotes;
use App\Console\Commands\ExpireTaskRuns;
use App\Console\Commands\GenerateRecurringInvoices;
use App\Console\Commands\GenerateScheduledJobs;
use App\Console\Commands\MarkOverdueInvoices;
use App\Console\Commands\PruneMobileSessions;
use App\Console\Commands\PurgeTemporaryUploadsCommand;
use App\Console\Commands\RemindTasks;
use App\Enums\TaskReminderSlotEnum;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Laravel Cloud freezes cron in UTC at deploy, so a local-time event would miss its wake-up after a clock change.
$atSlovakHour = static fn (Event $event, int $hour): Event => $event
    ->cron(sprintf('0 %d,%d * * *', $hour - 2, $hour - 1))
    ->when(static fn (): bool => now('Europe/Bratislava')->hour === $hour);

$atSlovakHour(Schedule::command(PurgeTemporaryUploadsCommand::class), 12);
$atSlovakHour(Schedule::command(MarkOverdueInvoices::class), 12);
$atSlovakHour(Schedule::command(GenerateRecurringInvoices::class), 12);
$atSlovakHour(Schedule::command(ExpireQuotes::class), 12);
$atSlovakHour(Schedule::command(CheckContractExpiry::class), 12);
$atSlovakHour(Schedule::command(GenerateScheduledJobs::class), 12);
$atSlovakHour(Schedule::command(ExpirePendingActions::class), 12);
$atSlovakHour(Schedule::command('sanctum:prune-expired --hours=24'), 12);
$atSlovakHour(Schedule::command(PruneMobileSessions::class), 12);
foreach (TaskReminderSlotEnum::cases() as $slot) {
    $atSlovakHour(Schedule::command(RemindTasks::class), $slot->hour())->withoutOverlapping();
}

$atSlovakHour(Schedule::command(ExpireTaskRuns::class), 12);
