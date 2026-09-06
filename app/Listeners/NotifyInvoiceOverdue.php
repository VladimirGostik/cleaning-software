<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationTypeEnum;
use App\Enums\PermissionEnum;
use App\Events\InvoiceMarkedOverdue;
use App\Models\Invoice;
use App\Notifications\InvoiceOverdue;
use App\Scopes\TenantScope;
use App\Services\NotificationRecipientResolver;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class NotifyInvoiceOverdue
{
    public function __construct(private readonly NotificationRecipientResolver $recipients) {}

    public function handle(InvoiceMarkedOverdue $event): void
    {
        $users = $this->recipients->usersWithPermission($event->tenantId, PermissionEnum::ViewInvoices);

        if ($users->isEmpty()) {
            return;
        }

        $invoice = Invoice::withoutGlobalScope(TenantScope::class)->findOrFail($event->invoiceId);

        NotificationFacade::send($users, new InvoiceOverdue(
            $event->tenantId,
            $event->invoiceId,
            $invoice->number ?? __('app.invoice_pdf_draft'),
        ));

        logger()->info('notification.dispatched', [
            'type' => NotificationTypeEnum::InvoiceOverdue->value,
            'tenant_id' => $event->tenantId,
            'invoice_id' => $event->invoiceId,
            'recipients' => $users->count(),
        ]);
    }
}
