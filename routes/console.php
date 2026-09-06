<?php

declare(strict_types=1);

use App\Console\Commands\CheckContractExpiry;
use App\Console\Commands\ExpireQuotes;
use App\Console\Commands\GenerateRecurringInvoices;
use App\Console\Commands\GenerateScheduledJobs;
use App\Console\Commands\MarkOverdueInvoices;
use App\Console\Commands\PurgeTemporaryUploadsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(PurgeTemporaryUploadsCommand::class)->daily();
Schedule::command(MarkOverdueInvoices::class)->daily();
Schedule::command(GenerateRecurringInvoices::class)->daily();
Schedule::command(ExpireQuotes::class)->daily();
Schedule::command(CheckContractExpiry::class)->daily();
Schedule::command(GenerateScheduledJobs::class)->daily();
