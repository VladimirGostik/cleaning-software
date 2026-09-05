<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\RendersInvoicePdf;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

final class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(RendersInvoicePdf::class, function (MockInterface $mock): void {
            $mock->shouldReceive('render')->andReturn('%PDF-1.4 fake pdf content');
        });
    }

    // -------------------------------------------------------------------------
    // Happy path — each template renders as PDF
    // -------------------------------------------------------------------------

    public function test_classic_template_pdf_downloads_for_issued_invoice(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'template' => InvoiceTemplateEnum::Classic,
            'customer_name' => 'Test Corp s.r.o.',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_modern_template_pdf_downloads(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'template' => InvoiceTemplateEnum::Modern,
            'customer_name' => 'Modern Client',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_minimal_template_pdf_downloads(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'template' => InvoiceTemplateEnum::Minimal,
            'customer_name' => 'Minimal Client',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_draft_invoice_pdf_downloads_without_number(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'template' => InvoiceTemplateEnum::Classic,
            'status' => InvoiceStatusEnum::Draft,
            'number' => null,
            'customer_name' => 'Draft Customer',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_renders_without_error_when_iban_missing(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'supplier_name' => $tenant->name,
            'supplier_iban' => null,
        ]);

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_renders_for_draft_invoice_without_qr(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatusEnum::Draft,
            'supplier_name' => $tenant->name,
            'supplier_iban' => 'SK3112000000198742637541',
        ]);

        // Draft status means QR should be null (status != Issued|Overdue)
        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_download_pdf(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'supplier_name' => $tenant->name,
        ]);

        $this->post(route('logout'));

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_view_permission_cannot_download_pdf(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertForbidden();
    }

    public function test_cross_tenant_invoice_pdf_returns_404(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $otherTenant = Tenant::factory()->create();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $otherTenant->id,
            'supplier_name' => 'Other tenant',
        ]);

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertNotFound();
    }
}
