<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\RendersInvoicePdf;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(120)]
final class InvoiceIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $invoiceId,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::withTrashed()->findOrFail($this->invoiceId);

        $pdfContent = app(RendersInvoicePdf::class)->render($invoice);

        $filename = ($invoice->number ?? 'draft') . '.pdf';

        return (new MailMessage)
            ->subject(__('app.invoices.mail.subject', ['number' => $invoice->number ?? __('app.invoices.pdf.draft')]))
            ->view('mail.invoice', ['invoice' => $invoice])
            ->attachData($pdfContent, $filename, ['mime' => 'application/pdf']);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('invoice.email.send.failed', [
            'invoice_id' => $this->invoiceId,
            'exception' => $e?->getMessage(),
            'trace' => $e?->getTraceAsString(),
        ]);
    }
}
