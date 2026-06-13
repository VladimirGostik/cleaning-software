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

    /** Returns raw PDF bytes using Spatie PDF (Browsershot). All display values come from snapshot columns. */
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing('items');

        $qrDataUri = $this->qrGenerator->dataUri($invoice);

        return Pdf::view($invoice->template->view(), [
            'invoice' => $invoice,
            'qrDataUri' => $qrDataUri,
        ])
            ->format('A4')
            ->portrait()
            ->generatePdfContent();
    }
}
