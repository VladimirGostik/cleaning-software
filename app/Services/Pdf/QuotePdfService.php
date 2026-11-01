<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\RendersQuotePdf;
use App\Enums\DocumentTypeEnum;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\Documents\DocumentLocale;
use App\Services\Documents\DocumentPdfRenderer;
use App\Services\Documents\QuoteTextService;
use Spatie\LaravelPdf\Facades\Pdf;

final readonly class QuotePdfService implements RendersQuotePdf
{
    public function __construct(
        private QuoteTextService $text,
        private DocumentPdfRenderer $pdf,
        private DocumentLocale $locale,
    ) {}

    /** Returns raw PDF bytes via the `chrome` driver. Itemized quotes only — document-kind quotes serve the uploaded file directly. */
    public function render(Quote $quote): string
    {
        $quote->loadMissing(['items', 'client', 'cleaningObject', 'document']);

        // Not `current_tenant_id()` — rendering must also work from queue/console callers with no bound tenant.
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($quote->tenant_id);

        $body = $this->text->resolvedBody($quote);

        if ($body !== null && $quote->document !== null) {
            return $this->pdf->render(
                $quote->subject ?? $quote->number ?? $this->locale->run(fn (): string => __('app.quote_pdf_title')),
                $body,
                $tenant,
                DocumentTypeEnum::Quote,
                $quote->document->show_header,
                $quote->document->show_footer,
            );
        }

        return $this->locale->run(fn (): string => Pdf::view('pdf.quotes.default', ['quote' => $quote, 'tenant' => $tenant])
            ->format('A4')
            ->portrait()
            ->headerHtml('<div></div>')
            ->footerView('pdf.quotes.footer', ['tenant' => $tenant, 'preview' => false])
            ->margins(18, 18, 26, 18)
            ->generatePdfContent());
    }
}
