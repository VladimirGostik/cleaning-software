<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Invoice;
use App\Notifications\InvoiceIssued;
use App\Scopes\TenantScope;
use Illuminate\Notifications\Events\NotificationSent;

final class StampInvoiceSentAt
{
    public function handle(NotificationSent $event): void
    {
        if (! ($event->notification instanceof InvoiceIssued)) {
            return;
        }

        Invoice::withTrashed()->withoutGlobalScope(TenantScope::class)
            ->find($event->notification->invoiceId)
            ?->update(['sent_at' => now()]);
    }
}
