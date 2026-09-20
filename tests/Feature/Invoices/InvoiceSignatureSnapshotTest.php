<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\ResolvesSupplierSignature;
use App\Data\Invoices\InvoiceIssueData;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;
use Tests\TestCase;

final class InvoiceSignatureSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** Distinct `$width`/`$height` per call — GD's blank-canvas fixture is byte-identical for equal dimensions. */
    private function attachSignature(Tenant $tenant, string $name = 'signature.png', int $width = 80, int $height = 40): SpatieMedia
    {
        $media = $tenant->addMedia(UploadedFile::fake()->image($name, $width, $height))->toMediaCollection('signature');
        $tenant->update(['signature_media_id' => $media->id]);

        return $media;
    }

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_issuing_stamps_tenant_current_signature(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $signature = $this->attachSignature($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), null);

        $this->assertSame($signature->id, $issued->supplier_signature_media_id);
    }

    public function test_replacing_signature_after_issue_keeps_historical_render_but_draft_gets_new_one(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $this->attachSignature($tenant, 'original.png', 80, 40);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), null);

        $resolver = app(ResolvesSupplierSignature::class);
        $originalDataUri = $resolver->forInvoice($issued);

        // Owner replaces the signature.
        $newSignature = $this->attachSignature($tenant, 'replacement.png', 120, 60);
        $draft = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $issued->refresh();
        $stillOriginal = $resolver->forInvoice($issued);
        $draftDataUri = $resolver->forInvoice($draft);

        $this->assertSame($originalDataUri, $stillOriginal);
        $this->assertNotSame($stillOriginal, $draftDataUri);
        $this->assertSame($newSignature->id, $tenant->fresh()?->signature_media_id);
    }

    // -------------------------------------------------------------------------
    // failure / edge
    // -------------------------------------------------------------------------

    public function test_issuing_without_tenant_signature_leaves_column_null_and_still_issues(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), null);

        $this->assertNull($issued->supplier_signature_media_id);
        $this->assertNotNull($issued->number);
    }

    public function test_removing_signature_after_issue_does_not_affect_issued_invoice_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $signature = $this->attachSignature($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), null);

        $tenant->update(['signature_media_id' => null]);

        $issued->refresh();
        $this->assertSame($signature->id, $issued->supplier_signature_media_id);
    }

    public function test_deleting_referenced_media_row_nulls_column_via_fk_and_render_falls_back_without_exception(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $signature = $this->attachSignature($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), null);

        Media::query()->where('id', $signature->id)->delete();

        $issued->refresh();
        $this->assertNull($issued->supplier_signature_media_id);

        $dataUri = app(ResolvesSupplierSignature::class)->forInvoice($issued);
        $this->assertNull($dataUri);
    }

    public function test_duplicate_of_issued_invoice_has_null_signature_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $this->attachSignature($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), null);

        $duplicate = app(InvoiceService::class)->duplicate($issued);

        $this->assertNull($duplicate->supplier_signature_media_id);
    }
}
