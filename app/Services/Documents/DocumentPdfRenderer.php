<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\Documents\DocumentLetterheadData;
use App\Enums\DocumentTypeEnum;
use App\Models\Tenant;
use Spatie\LaravelPdf\Facades\Pdf;

final readonly class DocumentPdfRenderer
{
    private const int PAGE_MARGIN_MM = 20;

    private const float HEADER_LINE_MM = 4.8;

    private const float FOOTER_LINE_MM = 4.3;

    public function __construct(
        private DocumentTemplateRenderer $renderer,
        private DocumentLocale $locale,
    ) {}

    public function render(
        string $title,
        string $bodyHtml,
        Tenant $tenant,
        DocumentTypeEnum $type,
        bool $showHeader = true,
        bool $showFooter = true,
        ?string $reference = null,
    ): string {
        return $this->locale->run(fn (): string => $type === DocumentTypeEnum::Quote
            ? $this->generateQuote($title, $bodyHtml, $tenant, $showHeader, $showFooter, $reference)
            : $this->generate($title, $bodyHtml, $tenant, $type, $showHeader, $showFooter));
    }

    private function generateQuote(
        string $title,
        string $bodyHtml,
        Tenant $tenant,
        bool $showHeader,
        bool $showFooter,
        ?string $reference,
    ): string {
        return Pdf::view('pdf.quotes.default', [
            'title' => $title,
            'body' => $this->renderer->highlightMissing($bodyHtml, DocumentTypeEnum::Quote),
            'reference' => $reference,
            'showHeader' => $showHeader,
        ])
            ->format('A4')
            ->portrait()
            ->headerHtml('<div></div>')
            ->footerView('pdf.quotes.footer', ['show' => $showFooter, 'lines' => DocumentFrame::quoteFooter($tenant)])
            ->margins(14, 18, 17, 18)
            ->generatePdfContent();
    }

    private function generate(
        string $title,
        string $bodyHtml,
        Tenant $tenant,
        DocumentTypeEnum $type,
        bool $showHeader,
        bool $showFooter,
    ): string {
        $pdf = Pdf::view('pdf.documents.default', [
            'title' => $title,
            'body' => $this->renderer->highlightMissing($bodyHtml, $type),
        ])->format('A4')->portrait();

        $letterhead = DocumentFrame::letterhead($tenant);
        $footer = DocumentFrame::footer($tenant);

        $pdf->headerView('pdf.documents.header', [
            'show' => $showHeader,
            'letterhead' => $letterhead,
        ])->footerView('pdf.documents.footer', [
            'show' => $showFooter,
            'lines' => $footer,
        ])->margins(
            $showHeader ? self::PAGE_MARGIN_MM + $this->headerHeightMm($letterhead) : self::PAGE_MARGIN_MM,
            self::PAGE_MARGIN_MM,
            self::PAGE_MARGIN_MM + $this->footerHeightMm($showFooter ? count($footer) : 0),
            self::PAGE_MARGIN_MM,
        );

        return $pdf->generatePdfContent();
    }

    private function headerHeightMm(DocumentLetterheadData $letterhead): int
    {
        $lines = 1 + ($letterhead->address !== null ? 1 : 0) + ($letterhead->ico !== null ? 1 : 0);

        return (int) ceil($lines * self::HEADER_LINE_MM + 4);
    }

    private function footerHeightMm(int $lineCount): int
    {
        return (int) ceil(($lineCount + 1) * self::FOOTER_LINE_MM + 6);
    }
}
