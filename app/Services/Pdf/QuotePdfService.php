<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\RendersQuotePdf;
use App\Models\Quote;
use App\Models\Tenant;
use Spatie\LaravelPdf\Facades\Pdf;

final readonly class QuotePdfService implements RendersQuotePdf
{
    /** Returns raw PDF bytes via the `chrome` driver. Itemized quotes only — document-kind quotes serve the uploaded file directly. */
    public function render(Quote $quote): string
    {
        $quote->loadMissing(['items', 'client', 'cleaningObject']);

        // Not `current_tenant_id()` — rendering must also work from queue/console callers with no bound tenant.
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($quote->tenant_id);

        return Pdf::view('pdf.quotes.default', ['quote' => $quote, 'tenant' => $tenant])
            ->format('A4')
            ->portrait()
            ->generatePdfContent();
    }
}
