<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\ResolvesSupplierSignature;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Never calls `current_tenant_id()` — `forInvoice()` runs on the queued `InvoiceIssued` path with
 * no tenant bound to the container. Tenancy is always derived from the already-loaded model.
 */
final readonly class SupplierSignatureResolver implements ResolvesSupplierSignature
{
    public function forInvoice(Invoice $invoice): ?string
    {
        $media = $invoice->supplier_signature_media_id !== null
            ? Media::query()
                ->where('collection_name', 'signature')
                ->find($invoice->supplier_signature_media_id)
            : Tenant::withoutGlobalScopes()->find($invoice->tenant_id)?->signatureMedia;

        return $this->encode($media, $invoice->tenant_id);
    }

    public function forTenant(Tenant $tenant): ?string
    {
        return $this->encode($tenant->signatureMedia, $tenant->id);
    }

    private function encode(?Media $media, string $expectedTenantId): ?string
    {
        if ($media === null) {
            return null;
        }

        if ($media->tenant_id !== $expectedTenantId) {
            Log::warning('invoice.signature.tenant_mismatch', [
                'media_id' => $media->id,
                'expected_tenant_id' => $expectedTenantId,
            ]);

            return null;
        }

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        if (! $disk->exists($path)) {
            Log::warning('invoice.signature.file_missing', [
                'media_id' => $media->id,
                'expected_tenant_id' => $expectedTenantId,
            ]);

            return null;
        }

        $contents = $disk->get($path);

        if ($contents === null) {
            Log::warning('invoice.signature.file_missing', [
                'media_id' => $media->id,
                'expected_tenant_id' => $expectedTenantId,
            ]);

            return null;
        }

        return 'data:'.$media->mime_type.';base64,'.base64_encode($contents);
    }
}
