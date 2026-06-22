<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Invoice;
use App\Notifications\InvoiceIssued;
use Illuminate\Notifications\Events\NotificationSent;

final class StampInvoiceSentAt
{
    public function handle(NotificationSent $event): void
    {
        if (! ($event->notification instanceof InvoiceIssued)) {
            return;
        }

        /** @var InvoiceIssued $notification */
        $notification = $event->notification;

        Invoice::withTrashed()->find($notification->invoiceId)?->update(['sent_at' => now()]);
    }
}
