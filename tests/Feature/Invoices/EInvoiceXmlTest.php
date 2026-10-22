<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Enums\EfaStatusEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsEfaInvoices;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class EInvoiceXmlTest extends TestCase
{
    use BuildsEfaInvoices, CreatesUsers, RefreshDatabase;

    private function efaError(): string
    {
        $errors = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $errors);

        return $errors->first('efa');
    }

    /** @return list<array<string, mixed>> */
    private function items(): array
    {
        return [['description' => 'Upratovanie', 'quantity' => 1, 'unit' => 'hod', 'unit_price' => 50, 'line_base' => 50, 'line_vat' => 0, 'line_total' => 50]];
    }

    public function test_download_returns_schema_valid_xml_attachment_and_marks_ready(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = $this->efaInvoice($tenant, [], $this->items());
        $this->assertSame(EfaStatusEnum::None, $invoice->efa_status);

        $response = $this->get(route('invoices.efa.xml', $invoice));

        $response->assertOk();
        $this->assertStringStartsWith('application/xml', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString($invoice->number.'.xml', (string) $response->headers->get('Content-Disposition'));
        $xp = $this->loadUbl((string) $response->getContent());
        $this->assertSame($invoice->number, $this->xv($xp, '/*/cbc:ID'));
        $this->assertSame(EfaStatusEnum::Ready, $invoice->refresh()->efa_status);
    }

    public function test_download_does_not_downgrade_a_later_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = $this->efaInvoice($tenant, ['efa_status' => EfaStatusEnum::Accepted], $this->items());

        $this->get(route('invoices.efa.xml', $invoice))->assertOk();

        $this->assertSame(EfaStatusEnum::Accepted, $invoice->refresh()->efa_status);
    }

    public function test_draft_invoice_is_rejected_with_status_error(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('invoices.efa.xml', $invoice))->assertSessionHasErrors('status');
    }

    public function test_missing_supplier_ico_returns_efa_error_listing_the_field(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = $this->efaInvoice($tenant, ['supplier_ico' => null], $this->items());

        $response = $this->get(route('invoices.efa.xml', $invoice));

        $response->assertSessionHasErrors('efa');
        $this->assertStringContainsString(__('app.efa_missing_supplier_ico'), $this->efaError());
        $this->assertSame(EfaStatusEnum::None, $invoice->refresh()->efa_status);
    }

    public function test_view_only_user_can_download_but_cross_tenant_invoice_is_404(): void
    {
        $user = $this->userWithPermission('view invoices');
        $this->actingAs($user);
        $own = $this->efaInvoice(Tenant::query()->findOrFail(current_tenant_id()), [], $this->items());
        $foreign = Invoice::factory()->issued()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->get(route('invoices.efa.xml', $own))->assertOk();
        $this->get(route('invoices.efa.xml', $foreign))->assertNotFound();
    }

    public function test_user_without_view_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithPermission('view clients'));
        $invoice = $this->efaInvoice(Tenant::query()->findOrFail(current_tenant_id()), [], $this->items());

        $this->get(route('invoices.efa.xml', $invoice))->assertForbidden();
    }

    public function test_show_page_exposes_efa_status_and_missing_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = $this->efaInvoice($tenant, ['supplier_ico' => null, 'customer_ico' => null], $this->items());

        $this->get(route('invoices.show', $invoice))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('invoice.efa.status', 'none')
                ->where('invoice.efa.xml_available', false)
                ->where('invoice.efa.missing_fields', ['supplier_ico', 'customer_ico'])
                ->where('invoice.efa.efa_id', null),
        );
    }

    public function test_show_page_marks_xml_available_when_ready(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = $this->efaInvoice($tenant, [], $this->items());

        $this->get(route('invoices.show', $invoice))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('invoice.efa.xml_available', true)
                ->where('invoice.efa.missing_fields', []),
        );
    }

    public function test_draft_show_has_no_missing_fields_and_no_xml(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('invoices.show', $invoice))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('invoice.efa.xml_available', false)
                ->where('invoice.efa.missing_fields', []),
        );
    }
}
