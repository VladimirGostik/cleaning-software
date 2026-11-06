<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\Page;
use Spatie\LaravelPdf\Drivers\ChromeDriver;
use Spatie\LaravelPdf\PdfOptions;

final class ReusableChromeDriver extends ChromeDriver
{
    private ?ProcessAwareBrowser $browser = null;

    private ?Page $page = null;

    public function generatePdf(string $html, ?string $headerHtml, ?string $footerHtml, PdfOptions $options): string
    {
        $this->browser ??= $this->createBrowser();
        $this->page ??= $this->browser->createPage();

        $this->page->setHtml($html, $this->intConfig('timeout') ?? 30000);
        $this->waitForReadiness($this->page, $options);

        $pdf = $this->page->pdf($this->buildPdfOptions($headerHtml, $footerHtml, $options));

        return $pdf->getRawBinary($this->intConfig('operation_timeout'));
    }

    public function close(): void
    {
        $browser = $this->browser;
        $this->browser = null;
        $this->page = null;

        $browser?->close();
    }

    private function intConfig(string $key): ?int
    {
        $value = $this->config[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
