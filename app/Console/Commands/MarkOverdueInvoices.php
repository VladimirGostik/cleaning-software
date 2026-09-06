<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatusEnum;
use App\Events\InvoiceMarkedOverdue;
use App\Models\Invoice;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;

final class MarkOverdueInvoices extends Command
{
    protected $signature = 'app:mark-overdue-invoices';

    protected $description = 'Mark issued invoices with passed due date as overdue';

    public function handle(): int
    {
        Invoice::withoutGlobalScope(TenantScope::class)
            ->where('status', InvoiceStatusEnum::Issued->value)
            ->where('due_date', '<', now()->toDateString())
            ->whereNull('credited_invoice_id')
            ->lazyById(500)
            ->each(function (Invoice $invoice): void {
                $invoice->update(['status' => InvoiceStatusEnum::Overdue]);

                // Phase 5: notifications module subscribes to InvoiceMarkedOverdue
                InvoiceMarkedOverdue::dispatch($invoice->tenant_id, $invoice->id);
            });

        $this->info('Overdue invoices marked.');

        return self::SUCCESS;
    }
}
