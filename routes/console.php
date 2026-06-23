<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:mark-overdue-invoices')->daily();
Schedule::command('app:generate-recurring-invoices')->daily();
Schedule::command('app:check-contract-expiry')->daily();
Schedule::command('app:expire-quotes')->daily();
Schedule::command('app:generate-scheduled-jobs')->daily();
