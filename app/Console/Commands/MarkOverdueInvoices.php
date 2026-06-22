<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Invoice;
use App\Notifications\InvoiceOverdue;
use App\Scopes\TenantScope;
use App\Services\NotificationRecipientResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

final class MarkOverdueInvoices extends Command
{
    protected $signature = 'app:mark-overdue-invoices';

    protected $description = 'Mark issued invoices with passed due date as overdue';

    public function handle(NotificationRecipientResolver $resolver): int
    {
        /** @var array<string, list<string>> $overdueByTenant */
        $overdueByTenant = [];

        Invoice::withoutGlobalScope(TenantScope::class)
            ->where('status', InvoiceStatusEnum::Issued->value)
            ->where('due_date', '<', now()->toDateString())
            ->whereNull('credited_invoice_id')
            ->lazyById(500)
            ->each(function (Invoice $invoice) use (&$overdueByTenant): void {
                $invoice->update([
                    'status' => InvoiceStatusEnum::Overdue,
                ]);

                $overdueByTenant[$invoice->tenant_id][] = $invoice->id;
            });

        foreach ($overdueByTenant as $tenantId => $invoiceIds) {
            $recipients = $resolver->usersWithPermission($tenantId, PermissionEnum::ViewInvoices);

            foreach ($invoiceIds as $invoiceId) {
                Notification::send($recipients, new InvoiceOverdue($tenantId, $invoiceId));
            }
        }

        $this->info('Overdue invoices marked.');

        return self::SUCCESS;
    }
}
