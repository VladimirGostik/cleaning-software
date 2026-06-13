<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Daily command: flips Issued invoices past due_date to Overdue across all tenants.
 *
 * Owner notification deferred — notification module not yet implemented.
 * Job module linkage (unbilled jobs) deferred — jobs module not yet built.
 */
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
                $invoice->update([
                    'status' => InvoiceStatusEnum::Overdue,
                ]);
            });

        $this->info('Overdue invoices marked.');

        return self::SUCCESS;
    }
}
