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

        return $this->generate(
            $quote,
            $this->text->printBody($quote),
            $quote->textDocument()->show_header ?? true,
            $quote->textDocument()->show_footer ?? true,
        );
    }

    /** PDF of an unsaved quote (form preview) with the text the form would store. */
    public function renderPreview(Quote $quote, string $tokenBody, bool $showHeader, bool $showFooter): string
    {
        return $this->generate($quote, $this->text->printBody($quote, $tokenBody), $showHeader, $showFooter);
    }

    private function generate(Quote $quote, string $body, bool $showHeader, bool $showFooter): string
    {
        // Not `current_tenant_id()` — rendering must also work from queue/console callers with no bound tenant.
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($quote->tenant_id);

        return $this->pdf->render(
            $quote->subject ?? $quote->number ?? $this->locale->run(fn (): string => __('app.quote_pdf_title')),
            $body,
            $tenant,
            DocumentTypeEnum::Quote,
            $showHeader,
            $showFooter,
            $quote->number,
        );
    }
}
