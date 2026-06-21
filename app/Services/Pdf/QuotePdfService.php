<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\RendersQuotePdf;
use App\Models\Quote;
use App\Models\Tenant;
use Spatie\LaravelPdf\Facades\Pdf;

final readonly class QuotePdfService implements RendersQuotePdf
{
    public function render(Quote $quote): string
    {
        $quote->loadMissing(['items', 'client', 'cleaningObject']);

        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail(app('current_tenant_id'));

        return Pdf::view('pdf.quotes.default', ['quote' => $quote, 'tenant' => $tenant])
            ->format('A4')
            ->portrait()
            ->generatePdfContent();
    }
}
