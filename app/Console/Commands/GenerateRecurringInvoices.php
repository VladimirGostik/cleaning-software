<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RecurringInvoiceStatusEnum;
use App\Jobs\GenerateRecurringInvoiceJob;
use App\Models\RecurringInvoice;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;

final class GenerateRecurringInvoices extends Command
{
    protected $signature = 'app:generate-recurring-invoices';

    protected $description = 'Dispatch jobs to generate invoices from due recurring templates';

    public function handle(): int
    {
        RecurringInvoice::withoutGlobalScope(TenantScope::class)
            ->where('status', RecurringInvoiceStatusEnum::Active->value)
            ->whereNotNull('next_run_at')
            ->whereDate('next_run_at', '<=', now()->toDateString())
            ->lazyById(500)
            ->each(fn (RecurringInvoice $ri) => GenerateRecurringInvoiceJob::dispatch($ri->id));

        $this->info('Recurring invoice generation jobs dispatched.');

        return self::SUCCESS;
    }
}
