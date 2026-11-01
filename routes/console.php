<?php

declare(strict_types=1);

use App\Console\Commands\CheckContractExpiry;
use App\Console\Commands\ExpirePendingActions;
use App\Console\Commands\ExpireQuotes;
use App\Console\Commands\GenerateRecurringInvoices;
use App\Console\Commands\GenerateScheduledJobs;
use App\Console\Commands\MarkOverdueInvoices;
use App\Console\Commands\PurgeTemporaryUploadsCommand;
use App\Console\Commands\RemindTasks;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$timezone = 'Europe/Bratislava';

Schedule::command(PurgeTemporaryUploadsCommand::class)->dailyAt('12:00')->timezone($timezone);
Schedule::command(MarkOverdueInvoices::class)->dailyAt('12:00')->timezone($timezone);
Schedule::command(GenerateRecurringInvoices::class)->dailyAt('12:00')->timezone($timezone);
Schedule::command(ExpireQuotes::class)->dailyAt('12:00')->timezone($timezone);
Schedule::command(CheckContractExpiry::class)->dailyAt('12:00')->timezone($timezone);
Schedule::command(GenerateScheduledJobs::class)->dailyAt('12:00')->timezone($timezone);
Schedule::command(ExpirePendingActions::class)->dailyAt('12:00')->timezone($timezone);
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('12:00')->timezone($timezone);
Schedule::command(RemindTasks::class)->dailyAt('19:00')->timezone($timezone)->withoutOverlapping();
