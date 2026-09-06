<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InvoiceCancelTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_cancel_issued_invoice_creates_credit_note(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->issued()->vatPayer()->withDeposit(20)->create(['tenant_id' => $tenant->id]);

        $result = app(InvoiceService::class)->cancel($invoice);
        $invoice->refresh();

        $this->assertSame(InvoiceStatusEnum::Cancelled, $invoice->status);
        $this->assertNotNull($invoice->cancelled_at);

        $creditNote = Invoice::where('credited_invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(InvoiceStatusEnum::Issued, $creditNote->status);
        $this->assertNotNull($creditNote->number);
        $this->assertNotSame($invoice->number, $creditNote->number);
        $this->assertSame('-123.00', $creditNote->total);
        $this->assertSame('-20.00', $creditNote->deposit);
        $this->assertNotNull($creditNote->vat_breakdown);
        $this->assertSame(-23, $creditNote->vat_breakdown[0]['vat']);
        $this->assertSame($invoice->customer_name, $creditNote->customer_name);
        $this->assertSame($invoice->supplier_name, $creditNote->supplier_name);

        $this->assertSame($invoice, $result);
    }

    public function test_cancel_overdue_invoice_works(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id]);

        app(InvoiceService::class)->cancel($invoice);
        $invoice->refresh();

        $this->assertSame(InvoiceStatusEnum::Cancelled, $invoice->status);
    }

    // -------------------------------------------------------------------------
    // failure
    // -------------------------------------------------------------------------

    public function test_cancel_draft_invoice_throws(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(InvoiceService::class)->cancel($invoice);
    }

    public function test_cancel_forbidden_without_cancel_invoices_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vedúca', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        $this->post(route('invoices.cancel', $invoice))->assertForbidden();
    }
}
