<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\RendersInvoicePdf;
use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Mockery\Expectation;
use Tests\TestCase;

final class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private function mockPdfRenderer(): void
    {
        /** @var Expectation $expectation */
        $expectation = $this->mock(RendersInvoicePdf::class)->shouldReceive('render');
        $expectation->once()->andReturn('%PDF-1.4 fake');
    }

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_download_pdf_for_classic_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'template' => 'classic']);
        $this->mockPdfRenderer();

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_download_pdf_for_modern_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'template' => 'modern']);
        $this->mockPdfRenderer();

        $this->get(route('invoices.pdf', $invoice))->assertOk();
    }

    public function test_download_pdf_for_minimal_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'template' => 'minimal']);
        $this->mockPdfRenderer();

        $this->get(route('invoices.pdf', $invoice))->assertOk();
    }

    public function test_download_pdf_draft_without_number_uses_draft_filename(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $this->mockPdfRenderer();

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=draft.pdf');
    }

    public function test_download_pdf_missing_iban_still_renders(): void
    {
        $tenant = Tenant::factory()->create(['iban' => null]);
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'supplier_iban' => null]);
        $this->mockPdfRenderer();

        $this->get(route('invoices.pdf', $invoice))->assertOk();
    }

    public function test_download_pdf_with_unsafe_characters_in_number_produces_well_formed_header(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'number' => 'FA"2026/01']);
        $this->mockPdfRenderer();

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        $this->assertStringStartsWith('attachment; filename=', $disposition);
        // The raw invoice number's `"` and `/` must never survive into the header value.
        $this->assertStringNotContainsString('/', $disposition);
        $this->assertSame('attachment; filename=FA202601.pdf', $disposition);
    }

    // -------------------------------------------------------------------------
    // failure
    // -------------------------------------------------------------------------

    public function test_download_pdf_unauthenticated_redirects_to_login(): void
    {
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('invoices.pdf', $invoice))->assertRedirect(route('login'));
    }

    public function test_download_pdf_without_permission_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('invoices.pdf', $invoice))->assertForbidden();
    }

    public function test_download_pdf_cross_tenant_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);
        $invoiceB = Invoice::factory()->create(['tenant_id' => $tenantB->id]);

        $this->get(route('invoices.pdf', $invoiceB->id))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // supplier snapshot -> template rendering
    // -------------------------------------------------------------------------

    public function test_classic_template_renders_full_supplier_snapshot(): void
    {
        $tenant = Tenant::factory()->create([
            'address_line' => 'Hlavná 1',
            'city' => 'Bratislava',
            'postal_code' => '811 01',
            'dic' => '2012345678',
            'vat_number' => 'SK2012345678',
            'is_vat_payer' => true,
            'iban' => 'SK8975000000000123456789',
            'swift_bic' => 'TATRSKBX',
            'contact_email' => 'fakturacia@democleaning.sk',
            'contact_phone' => '+421900000000',
        ]);
        $this->bindTenant($tenant);

        $invoice = app(InvoiceService::class)->create(new InvoiceUpsertData(
            client_id: null,
            cleaning_object_id: null,
            type: InvoiceTypeEnum::OneOff,
            template: InvoiceTemplateEnum::Classic,
            issue_date: now()->toDateString(),
            delivery_date: now()->toDateString(),
            due_date: now()->addDays(14)->toDateString(),
            period_from: null,
            period_to: null,
            customer_name: 'Acme s.r.o.',
            customer_representative: null,
            customer_ico: null,
            customer_dic: null,
            customer_vat_number: null,
            customer_street: null,
            customer_city: null,
            customer_postal_code: null,
            customer_country: null,
            customer_email: null,
            note: null,
            items: [new InvoiceItemData(id: null, description: 'Upratovanie', quantity: 1, unit: null, unit_price: 100)],
            constant_symbol: null,
            specific_symbol: null,
            header_text: null,
            footer_text: null,
        ));

        $html = View::make(InvoiceTemplateEnum::Classic->view(), ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringContainsString('Hlavná 1', $html);
        $this->assertStringContainsString('811 01 Bratislava', $html);
        $this->assertStringContainsString('2012345678', $html);
        $this->assertStringContainsString('SK2012345678', $html);
        $this->assertStringContainsString('SK8975000000000123456789', $html);
        $this->assertStringContainsString('TATRSKBX', $html);
        $this->assertStringContainsString('fakturacia@democleaning.sk', $html);
        $this->assertStringContainsString('+421900000000', $html);
    }
}
