<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RecurringInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class RemindInvoiceIssue extends Command
{
    protected $signature = 'app:remind-invoice-issue';

    protected $description = 'Remind invoice issuers that the month\'s recurring invoice drafts are coming';

    public function handle(RecurringInvoiceService $service): int
    {
        $sent = $service->sendIssueReminders(CarbonImmutable::now('Europe/Bratislava')->startOfDay());

        $this->info("Invoice issue reminders sent for {$sent} firms.");

        return self::SUCCESS;
    }
}
