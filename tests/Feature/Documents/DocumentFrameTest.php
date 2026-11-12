<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Enums\DocumentTypeEnum;
use App\Models\Tenant;
use App\Services\Documents\DocumentFrame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

final class DocumentFrameTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_formats_the_tenant_phone(): void
    {
        $tenant = Tenant::factory()->create(['contact_phone' => '+421900000000', 'contact_email' => 'info@example.sk']);

        $footer = DocumentFrame::footer($tenant);

        $this->assertContains('+421 900 000 000', $footer);
        $this->assertNotContains('+421900000000', $footer);
    }

    public function test_footer_skips_a_missing_phone(): void
    {
        $tenant = Tenant::factory()->create(['contact_phone' => null, 'contact_email' => 'info@example.sk']);

        $footer = DocumentFrame::footer($tenant);

        $this->assertContains('info@example.sk', $footer);
        $this->assertCount(count(array_unique($footer)), $footer);
    }

    public function test_quote_footer_has_two_columns_in_the_document_locale(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Firma s.r.o.', 'address_line' => 'Hlavná 1', 'postal_code' => '811 01', 'city' => 'Bratislava', 'ico' => '123', 'dic' => '456']);
        App::setLocale('en');

        $this->assertSame(['Firma s.r.o. · Hlavná 1, 811 01 Bratislava', 'IČO 123 · DIČ 456'], DocumentFrame::quoteFooter($tenant));
        $this->assertSame(DocumentFrame::quoteFooter($tenant), DocumentFrame::footer($tenant, DocumentTypeEnum::Quote));
        $this->assertNotSame(DocumentFrame::quoteFooter($tenant), DocumentFrame::footer($tenant, DocumentTypeEnum::ServiceAgreement));
    }

    public function test_quote_footer_keeps_two_entries_when_the_firm_has_no_ids(): void
    {
        $tenant = Tenant::factory()->create(['ico' => null, 'dic' => null]);

        $this->assertCount(2, DocumentFrame::quoteFooter($tenant));
        $this->assertSame('', DocumentFrame::quoteFooter($tenant)[1]);
    }
}
