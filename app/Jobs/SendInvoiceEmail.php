<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\InvoiceIssuedMail;
use App\Models\Invoice;
use App\Services\Pdf\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(120)]
final class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $invoiceId,
        public readonly string $recipientEmail,
    ) {}

    public function handle(InvoicePdfService $pdfService): void
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::withTrashed()->findOrFail($this->invoiceId);

        $pdfContent = $pdfService->render($invoice);

        Mail::to($this->recipientEmail)->send(new InvoiceIssuedMail($invoice, $pdfContent));

        $invoice->update(['sent_at' => now()]);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('invoice.email.send.failed', [
            'invoice_id' => $this->invoiceId,
            'recipient' => $this->recipientEmail,
            'exception' => $e?->getMessage(),
            'trace' => $e?->getTraceAsString(),
        ]);
    }
}
