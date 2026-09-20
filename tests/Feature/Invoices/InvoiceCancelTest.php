<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\ResolvesSupplierSignature;
use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $result = app(InvoiceService::class)->cancel($invoice, null);
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

        app(InvoiceService::class)->cancel($invoice, null);
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

        app(InvoiceService::class)->cancel($invoice, null);
    }

    public function test_cancel_forbidden_without_cancel_invoices_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vedúca', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        $this->post(route('invoices.cancel', $invoice))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // credit note — issuer + signature snapshot (gate 1b-ii)
    // -------------------------------------------------------------------------

    public function test_credit_note_carries_actors_issued_by_name_and_current_signature(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->update(['first_name' => 'Cancelling', 'last_name' => 'Actor']);
        $media = $tenant->addMedia(UploadedFile::fake()->image('sig.png', 80, 40))->toMediaCollection('signature');
        $tenant->update(['signature_media_id' => $media->id]);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        app(InvoiceService::class)->cancel($invoice, $user);

        $creditNote = Invoice::where('credited_invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('Cancelling Actor', $creditNote->issued_by_name);
        $this->assertSame($media->id, $creditNote->supplier_signature_media_id);
    }

    public function test_credit_note_signature_survives_later_tenant_signature_replacement(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $original = $tenant->addMedia(UploadedFile::fake()->image('original.png', 80, 40))->toMediaCollection('signature');
        $tenant->update(['signature_media_id' => $original->id]);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        app(InvoiceService::class)->cancel($invoice, null);
        $creditNote = Invoice::where('credited_invoice_id', $invoice->id)->firstOrFail();
        $resolver = app(ResolvesSupplierSignature::class);
        $originalDataUri = $resolver->forInvoice($creditNote);

        $replacement = $tenant->addMedia(UploadedFile::fake()->image('replacement.png', 120, 60))->toMediaCollection('signature');
        $tenant->update(['signature_media_id' => $replacement->id]);

        $creditNote->refresh();
        $this->assertSame($originalDataUri, $resolver->forInvoice($creditNote));
    }
}
