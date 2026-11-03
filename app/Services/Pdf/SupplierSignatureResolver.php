<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\ResolvesSupplierSignature;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\Tenant;

/**
 * Never calls `current_tenant_id()` — `forInvoice()` runs on the queued `InvoiceIssued` path with
 * no tenant bound to the container. Tenancy is always derived from the already-loaded model.
 */
final readonly class SupplierSignatureResolver implements ResolvesSupplierSignature
{
    public function __construct(private MediaDataUri $encoder) {}

    public function forInvoice(Invoice $invoice): ?string
    {
        $media = $invoice->supplier_signature_media_id !== null
            ? Media::query()
                ->where('collection_name', 'signature')
                ->find($invoice->supplier_signature_media_id)
            : Tenant::withoutGlobalScopes()->find($invoice->tenant_id)?->signatureMedia;

        return $this->encoder->encode($media, $invoice->tenant_id, 'invoice.signature');
    }

    public function forTenant(Tenant $tenant): ?string
    {
        return $this->encoder->encode($tenant->signatureMedia, $tenant->id, 'invoice.signature');
    }
}
