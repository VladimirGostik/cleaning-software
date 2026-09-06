<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Data\Invoices\InvoiceIssueData;
use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InvoiceIssueTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_issue_generates_auto_number_in_configured_format(): void
    {
        $tenant = Tenant::factory()->create(['invoice_number_format' => 'FA-{YYYY}-{XXXX}']);
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => now()->toDateString()]);

        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null));

        $this->assertSame('FA-'.now()->format('Y').'-0001', $issued->number);
        $this->assertSame(InvoiceStatusEnum::Issued, $issued->status);
        $this->assertNotNull($issued->issued_at);
    }

    public function test_issue_numbers_are_consecutive_per_tenant(): void
    {
        $tenant = Tenant::factory()->create(['invoice_number_format' => 'FA-{YYYY}-{XXXX}']);
        $this->bindTenant($tenant);
        $service = app(InvoiceService::class);

        $first = $service->issue(Invoice::factory()->create(['tenant_id' => $tenant->id]), new InvoiceIssueData(number: null));
        $second = $service->issue(Invoice::factory()->create(['tenant_id' => $tenant->id]), new InvoiceIssueData(number: null));

        $this->assertSame('FA-'.now()->format('Y').'-0001', $first->number);
        $this->assertSame('FA-'.now()->format('Y').'-0002', $second->number);
    }

    public function test_issue_numbering_is_independent_per_tenant(): void
    {
        $tenantA = Tenant::factory()->create(['invoice_number_format' => 'FA-{YYYY}-{XXXX}']);
        $tenantB = Tenant::factory()->create(['invoice_number_format' => 'FA-{YYYY}-{XXXX}']);
        $service = app(InvoiceService::class);

        $this->bindTenant($tenantA);
        $invoiceA = $service->issue(Invoice::factory()->create(['tenant_id' => $tenantA->id]), new InvoiceIssueData(number: null));

        $this->bindTenant($tenantB);
        $invoiceB = $service->issue(Invoice::factory()->create(['tenant_id' => $tenantB->id]), new InvoiceIssueData(number: null));

        $this->assertSame('FA-'.now()->format('Y').'-0001', $invoiceA->number);
        $this->assertSame('FA-'.now()->format('Y').'-0001', $invoiceB->number);
    }

    public function test_issue_accepts_manual_number_override_without_touching_sequence(): void
    {
        $tenant = Tenant::factory()->create(['invoice_number_format' => 'FA-{YYYY}-{XXXX}']);
        $this->bindTenant($tenant);
        $service = app(InvoiceService::class);

        $manual = $service->issue(Invoice::factory()->create(['tenant_id' => $tenant->id]), new InvoiceIssueData(number: 'CUSTOM-001'));
        $auto = $service->issue(Invoice::factory()->create(['tenant_id' => $tenant->id]), new InvoiceIssueData(number: null));

        $this->assertSame('CUSTOM-001', $manual->number);
        $this->assertSame('FA-'.now()->format('Y').'-0001', $auto->number);
    }

    public function test_issue_auto_number_skips_a_manually_taken_value(): void
    {
        $tenant = Tenant::factory()->create(['invoice_number_format' => 'FA-{YYYY}-{XXXX}']);
        $this->bindTenant($tenant);
        $service = app(InvoiceService::class);

        $service->issue(Invoice::factory()->create(['tenant_id' => $tenant->id]), new InvoiceIssueData(number: 'FA-'.now()->format('Y').'-0001'));
        $next = $service->issue(Invoice::factory()->create(['tenant_id' => $tenant->id]), new InvoiceIssueData(number: null));

        $this->assertSame('FA-'.now()->format('Y').'-0002', $next->number);
    }

    public function test_issue_custom_format_with_month_and_short_year(): void
    {
        $tenant = Tenant::factory()->create(['invoice_number_format' => '{YY}{MM}{XXX}']);
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => now()->toDateString()]);

        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null));

        $expected = now()->format('y').now()->format('m').'001';
        $this->assertSame($expected, $issued->number);
    }

    // -------------------------------------------------------------------------
    // failure
    // -------------------------------------------------------------------------

    public function test_issue_already_issued_invoice_throws(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null));
    }

    public function test_issue_duplicate_manual_number_in_same_tenant_throws(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $service = app(InvoiceService::class);
        $service->issue(Invoice::factory()->create(['tenant_id' => $tenant->id]), new InvoiceIssueData(number: 'DUP-001'));

        $this->expectException(ValidationException::class);

        $service->issue(Invoice::factory()->create(['tenant_id' => $tenant->id]), new InvoiceIssueData(number: 'DUP-001'));
    }

    public function test_issue_same_manual_number_in_different_tenant_is_allowed(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $service = app(InvoiceService::class);

        $this->bindTenant($tenantA);
        $service->issue(Invoice::factory()->create(['tenant_id' => $tenantA->id]), new InvoiceIssueData(number: 'SAME-001'));

        $this->bindTenant($tenantB);
        $issuedB = $service->issue(Invoice::factory()->create(['tenant_id' => $tenantB->id]), new InvoiceIssueData(number: 'SAME-001'));

        $this->assertSame('SAME-001', $issuedB->number);
    }

    // -------------------------------------------------------------------------
    // supplier completeness guard
    // -------------------------------------------------------------------------

    public function test_issue_blocked_when_supplier_address_line_missing(): void
    {
        $tenant = Tenant::factory()->create(['address_line' => null]);
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        try {
            app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null));
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('supplier', $e->errors());
        }

        $invoice->refresh();
        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->status);
        $this->assertNull($invoice->number);
    }

    public function test_issue_blocked_when_vat_payer_missing_vat_number(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => true, 'vat_number' => null]);
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null));
    }

    public function test_issue_allowed_for_non_vat_payer_without_dic_or_vat_number(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false, 'dic' => null, 'vat_number' => null]);
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null));

        $this->assertSame(InvoiceStatusEnum::Issued, $issued->status);
    }

    public function test_draft_create_and_update_are_allowed_on_incomplete_tenant(): void
    {
        $tenant = Tenant::factory()->create(['address_line' => null]);
        $this->bindTenant($tenant);

        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->status);
    }

    public function test_http_issue_on_incomplete_tenant_returns_session_errors(): void
    {
        $tenant = Tenant::factory()->create(['address_line' => null]);
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('invoices.issue', $invoice), ['number' => null]);

        $response->assertSessionHasErrors('supplier');
        $invoice->refresh();
        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->status);
    }
}
