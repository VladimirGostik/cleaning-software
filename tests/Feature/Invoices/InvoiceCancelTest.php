<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\ResolvesSupplierSignature;
use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\InvoiceNumberService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\AssertsFullRows;
use Tests\Support\BuildsFullInvoices;
use Tests\TestCase;

final class InvoiceCancelTest extends TestCase
{
    use AssertsFullRows;
    use BuildsFullInvoices;
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

    // -------------------------------------------------------------------------
    // credit note — full column characterization
    // -------------------------------------------------------------------------

    /** @return array{0: Tenant, 1: User, 2: Media} */
    private function cancellationFixture(): array
    {
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(10, 0, 0));
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->update(['first_name' => 'Cancelling', 'last_name' => 'Actor']);
        $current = $tenant->addMedia(UploadedFile::fake()->image('current-signature.png', 80, 40))->toMediaCollection('signature');
        $tenant->update(['signature_media_id' => $current->id]);

        return [$tenant, $user, $current];
    }

    public function test_credit_note_row_copies_snapshot_negates_money_and_resets_lifecycle_columns(): void
    {
        [$tenant, $user, $currentSignature] = $this->cancellationFixture();
        $source = $this->fullInvoiceSource($tenant, ['status' => 'issued']);

        app(InvoiceService::class)->cancel($source, $user);

        $creditNote = Invoice::query()->where('credited_invoice_id', $source->id)->sole();
        $this->assertNotNull($creditNote->number);
        $this->assertNotSame('FA-2026-0042', $creditNote->number);
        $this->assertSame(app(InvoiceNumberService::class)->variableSymbol($creditNote->number), $creditNote->variable_symbol);

        $this->assertFullRow($creditNote, [
            ...$this->carriedInvoiceColumns(),
            'tenant_id' => $source->tenant_id,
            'client_id' => $source->client_id,
            'cleaning_object_id' => $source->cleaning_object_id,
            'credited_invoice_id' => $source->id,
            'status' => 'issued',
            'number' => $creditNote->number,
            'variable_symbol' => $creditNote->variable_symbol,
            'subtotal' => '-300.00',
            'vat_amount' => '-60.00',
            'total' => '-360.00',
            'deposit' => '-10.00',
            'rounding_amount' => '-0.05',
            'vat_breakdown' => [['rate' => 20.0, 'base' => -300.0, 'vat' => -60.0, 'total' => -360.0]],
            'recurring_invoice_id' => null,
            'quote_id' => null,
            'period_from' => null,
            'period_to' => null,
            'issue_date' => '2026-11-04',
            'delivery_date' => '2026-11-04',
            'due_date' => '2026-11-04',
            'issued_at' => '2026-11-04 10:00:00',
            'issued_by_name' => 'Cancelling Actor',
            'sent_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
            'paid_amount' => '0.00',
            'supplier_signature_media_id' => $currentSignature->id,
            'efa_status' => 'none',
            'efa_id' => null,
            'efa_submitted_at' => null,
            'efa_last_error' => null,
            'deleted_at' => null,
        ]);
    }

    public function test_credit_note_items_negate_unit_price_and_line_amounts_and_copy_the_rest(): void
    {
        [$tenant, $user] = $this->cancellationFixture();
        $source = $this->fullInvoiceSource($tenant, ['status' => 'issued']);

        app(InvoiceService::class)->cancel($source, $user);

        $creditNote = Invoice::query()->where('credited_invoice_id', $source->id)->sole()->load('items');
        $this->assertCount(3, $creditNote->items);

        $negated = [
            ['unit_price' => '-40.00', 'line_base' => '-90.00', 'line_vat' => '-18.00', 'line_total' => '-108.00'],
            ['unit_price' => '-150.00', 'line_base' => '-150.00', 'line_vat' => '-30.00', 'line_total' => '-180.00'],
            ['unit_price' => '-20.00', 'line_base' => '-57.00', 'line_vat' => '0.00', 'line_total' => '-57.00'],
        ];

        $creditItems = $creditNote->items->all();

        foreach ($this->sourceItemColumns() as $index => $columns) {
            $this->assertFullRow($creditItems[$index], [
                ...$columns,
                ...$negated[$index],
                'tenant_id' => $source->tenant_id,
                'invoice_id' => $creditNote->id,
            ]);
        }

        $this->assertSame(3, InvoiceItem::query()->where('invoice_id', $source->id)->count());
    }

    public function test_credit_note_source_is_marked_cancelled_and_keeps_its_other_columns(): void
    {
        [$tenant, $user] = $this->cancellationFixture();
        $source = $this->fullInvoiceSource($tenant, ['status' => 'issued', 'cancelled_at' => null]);

        app(InvoiceService::class)->cancel($source, $user);
        $source->refresh();

        $this->assertSame(InvoiceStatusEnum::Cancelled, $source->status);
        $this->assertSame('2026-11-04 10:00:00', $source->cancelled_at?->format('Y-m-d H:i:s'));
        $this->assertSame('FA-2026-0042', $source->number);
        $this->assertSame('360.00', $source->total);
        $this->assertSame('2026-10-07 10:00:00', $source->efa_submitted_at?->format('Y-m-d H:i:s'));
    }

    public function test_credit_note_writes_a_created_activity_row_with_the_logged_attributes(): void
    {
        [$tenant, $user] = $this->cancellationFixture();
        $source = $this->fullInvoiceSource($tenant, ['status' => 'issued']);

        app(InvoiceService::class)->cancel($source, $user);

        $creditNote = Invoice::query()->where('credited_invoice_id', $source->id)->sole();
        $this->assertSame([
            'status' => 'issued',
            'number' => $creditNote->number,
            'total' => '-360.00',
            'client_id' => $source->client_id,
            'issued_at' => '2026-11-04T10:00:00.000000Z',
            'paid_at' => null,
            'cancelled_at' => null,
            'sent_at' => null,
            'efa_status' => 'none',
        ], $this->createdActivityAttributes($creditNote));
    }

    public function test_credit_note_of_invoice_without_items_or_breakdown_has_empty_breakdown_and_no_items(): void
    {
        [$tenant, $user] = $this->cancellationFixture();
        $source = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'vat_breakdown' => null]);

        app(InvoiceService::class)->cancel($source, $user);

        $creditNote = Invoice::query()->where('credited_invoice_id', $source->id)->sole()->load('items');
        $this->assertSame('-100.00', $creditNote->total);
        $this->assertSame([], $creditNote->vat_breakdown);
        $this->assertCount(0, $creditNote->items);
    }

    public function test_cancel_of_paid_invoice_is_rejected_and_creates_no_credit_note(): void
    {
        [$tenant, $user] = $this->cancellationFixture();
        $source = $this->fullInvoiceSource($tenant);

        try {
            app(InvoiceService::class)->cancel($source, $user);
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
        }

        $this->assertSame(0, Invoice::query()->where('credited_invoice_id', $source->id)->count());
    }
}
