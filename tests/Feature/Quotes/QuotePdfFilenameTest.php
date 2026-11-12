<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Contracts\RendersQuotePdf;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuotePdfFilenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbered_quote_uses_its_number(): void
    {
        $this->actingAsTenantUser();
        $quote = Quote::factory()->numbered()->create(['tenant_id' => current_tenant_id(), 'number' => 'CP-2026-0007']);

        $this->assertSame('CP-2026-0007', $quote->pdfFilenameBase());
    }

    public function test_unnumbered_quote_uses_the_subject_slug_and_never_draft(): void
    {
        $this->actingAsTenantUser();
        $quote = Quote::factory()->sent()->create(['tenant_id' => current_tenant_id(), 'number' => null, 'subject' => 'Zimná údržba Rajecká 16']);

        $this->assertSame('Cenova-ponuka-zimna-udrzba-rajecka-16', $quote->pdfFilenameBase());
    }

    public function test_quote_without_number_and_subject_uses_the_issue_date(): void
    {
        $this->actingAsTenantUser();
        $quote = Quote::factory()->create(['tenant_id' => current_tenant_id(), 'number' => null, 'subject' => null, 'issue_date' => '2026-11-11']);

        $this->assertSame('Cenova-ponuka-2026-11-11', $quote->pdfFilenameBase());
    }

    public function test_pdf_download_of_an_unnumbered_sent_quote_names_the_file_from_the_subject(): void
    {
        $this->actingAsTenantUser();
        $this->app->instance(RendersQuotePdf::class, new class implements RendersQuotePdf
        {
            public function render(Quote $quote): string
            {
                return '%PDF-quote';
            }
        });
        $quote = Quote::factory()->sent()->create(['tenant_id' => current_tenant_id(), 'number' => null, 'subject' => 'Zimná údržba Rajecká 16']);

        $response = $this->get(route('quotes.pdf', $quote))->assertOk();

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('Cenova-ponuka-zimna-udrzba-rajecka-16.pdf', $disposition);
        $this->assertStringNotContainsStringIgnoringCase('draft', $disposition);
    }
}
