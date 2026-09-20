<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\ResolvesSupplierSignature;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Pdf\SupplierSignatureResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class SupplierSignatureResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function tenantWithSignature(): Tenant
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $media = $tenant->addMedia(UploadedFile::fake()->image('signature.png', 100, 60))->toMediaCollection('signature');
        $tenant->update(['signature_media_id' => $media->id]);

        return $tenant->fresh() ?? $tenant;
    }

    private function unbindTenantContext(): void
    {
        app()->offsetUnset('current_tenant_id');
    }

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_for_tenant_resolves_data_uri_outside_http_request(): void
    {
        $tenant = $this->tenantWithSignature();

        $this->unbindTenantContext();
        $this->assertFalse(app()->bound('current_tenant_id'));

        $dataUri = app(SupplierSignatureResolver::class)->forTenant($tenant);

        $this->assertNotNull($dataUri);
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function test_for_invoice_resolves_tenant_current_signature_when_no_snapshot(): void
    {
        $tenant = $this->tenantWithSignature();
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'supplier_signature_media_id' => null]);

        $this->unbindTenantContext();

        $dataUri = app(ResolvesSupplierSignature::class)->forInvoice($invoice);

        $this->assertNotNull($dataUri);
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function test_for_invoice_resolves_snapshot_media_when_present(): void
    {
        $tenant = $this->tenantWithSignature();
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'supplier_signature_media_id' => $tenant->signature_media_id,
        ]);

        $this->unbindTenantContext();

        $dataUri = app(SupplierSignatureResolver::class)->forInvoice($invoice);

        $this->assertNotNull($dataUri);
    }

    public function test_for_invoice_returns_null_when_no_signature_anywhere(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->unbindTenantContext();

        $this->assertNull(app(SupplierSignatureResolver::class)->forInvoice($invoice));
    }

    // -------------------------------------------------------------------------
    // failure — fail-closed
    // -------------------------------------------------------------------------

    public function test_tenant_mismatch_returns_null_and_logs_warning(): void
    {
        $tenant = $this->tenantWithSignature();
        $otherTenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->create([
            'tenant_id' => $otherTenant->id,
            'supplier_signature_media_id' => $tenant->signature_media_id,
        ]);

        $this->unbindTenantContext();

        Log::shouldReceive('warning')
            ->once()
            ->with('invoice.signature.tenant_mismatch', Mockery::on(
                fn (array $context): bool => $context['expected_tenant_id'] === $otherTenant->id,
            ));

        $this->assertNull(app(SupplierSignatureResolver::class)->forInvoice($invoice));
    }

    public function test_missing_file_on_disk_returns_null_and_logs_warning_without_exception(): void
    {
        $tenant = $this->tenantWithSignature();
        $media = $tenant->signatureMedia;
        $this->assertNotNull($media);

        Storage::disk($media->disk)->delete($media->getPathRelativeToRoot());

        $this->unbindTenantContext();

        Log::shouldReceive('warning')
            ->once()
            ->with('invoice.signature.file_missing', Mockery::on(
                fn (array $context): bool => $context['media_id'] === $media->id,
            ));

        $this->assertNull(app(SupplierSignatureResolver::class)->forTenant($tenant));
    }
}
