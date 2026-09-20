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
        $invoice->loadMissing('items');

        $pdf = Pdf::view($invoice->template->view(), [
            'invoice' => $invoice,
            'qrDataUri' => $this->qrGenerator->dataUri($invoice),
            'signatureDataUri' => $this->signatures->forInvoice($invoice),
        ])
            ->format('A4')
            ->portrait();

        $footerView = $invoice->template->footerView();

        if ($footerView !== null) {
            // Only Classic has a footer view today; the wider gutters + bottom margin
            // give the two-row footer (dotted rule + page number) room to breathe.
            //
            // Chrome's `displayHeaderFooter` flips on for *both* header and footer as soon as
            // either is set (see ChromeDriver::buildPdfOptions()). Without an explicit header
            // template we'd get Chromium's own default header (render date + document title)
            // stamped on every page — an empty template is the only way to suppress it.
            $pdf->headerHtml('<div></div>')
                ->footerView($footerView, ['invoice' => $invoice, 'preview' => false])
                ->margins(12, 15, 22, 15);
        }

        return $pdf->generatePdfContent();
    }
}
