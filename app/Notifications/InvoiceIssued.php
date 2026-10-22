<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\RendersInvoicePdf;
use App\Models\Invoice;
use App\Models\TenantInterface;
use App\Scopes\TenantScope;
use App\Services\PlaceholderResolverService;
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
        $invoice = Invoice::withTrashed()->withoutGlobalScope(TenantScope::class)->findOrFail($this->invoiceId);

        $pdfContent = app(RendersInvoicePdf::class)->render($invoice);

        $filename = $invoice->pdfFilenameBase().'.pdf';

        $interface = TenantInterface::query()->where('tenant_id', $invoice->tenant_id)->first();
        $placeholders = app(PlaceholderResolverService::class);
        $variables = $placeholders->invoiceMailVariables($invoice);

        $subject = $this->filled($interface?->invoice_mail_subject)
            ? $placeholders->resolve((string) $interface?->invoice_mail_subject, $variables)
            : __('app.invoice_mail_subject', ['number' => $invoice->number ?? __('app.invoice_pdf_draft')]);

        $bodyText = $this->filled($interface?->invoice_mail_body)
            ? $placeholders->resolve((string) $interface?->invoice_mail_body, $variables)
            : null;

        return (new MailMessage)
            ->subject($subject)
            ->view('mail.invoice', ['invoice' => $invoice, 'bodyText' => $bodyText, 'mailSubject' => $subject])
            ->attachData($pdfContent, $filename, ['mime' => 'application/pdf']);
    }

    private function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
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
