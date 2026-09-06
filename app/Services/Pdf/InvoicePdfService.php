<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\GeneratesPaymentQr;
use App\Contracts\RendersInvoicePdf;
use App\Models\Invoice;
use Spatie\LaravelPdf\Facades\Pdf;

final readonly class InvoicePdfService implements RendersInvoicePdf
{
    public function __construct(private GeneratesPaymentQr $qrGenerator) {}

    /** Returns raw PDF bytes via the `chrome` driver. All display values come from snapshot columns. */
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing('items');

        return Pdf::view($invoice->template->view(), [
            'invoice' => $invoice,
            'qrDataUri' => $this->qrGenerator->dataUri($invoice),
        ])
            ->format('A4')
            ->portrait()
            ->generatePdfContent();
    }
}
