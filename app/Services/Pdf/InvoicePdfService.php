<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\GeneratesPaymentQr;
use App\Contracts\RendersInvoicePdf;
use App\Contracts\ResolvesSupplierSignature;
use App\Models\Invoice;
use Spatie\LaravelPdf\Facades\Pdf;

final readonly class InvoicePdfService implements RendersInvoicePdf
{
    public function __construct(
        private GeneratesPaymentQr $qrGenerator,
        private ResolvesSupplierSignature $signatures,
    ) {}

    /** Returns raw PDF bytes via the `chrome` driver. All display values come from snapshot columns. */
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'creditedInvoice']);

        return Pdf::view('pdf.invoices.kegos', [
            'invoice' => $invoice,
            'qrDataUri' => $this->qrGenerator->dataUri($invoice),
            'signatureDataUri' => $this->signatures->forInvoice($invoice),
        ])
            ->format('A4')
            ->portrait()
            // An explicit empty header stops Chrome stamping its default date/title header once the footer enables displayHeaderFooter.
            ->headerHtml('<div></div>')
            ->footerView('pdf.invoices.kegos-footer', ['invoice' => $invoice, 'preview' => false])
            ->margins(18, 18, 26, 18)
            ->generatePdfContent();
    }
}
