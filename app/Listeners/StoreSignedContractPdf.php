<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\RendersContractPdf;
use App\Events\ContractSigned;
use App\Models\Contract;
use App\Scopes\TenantScope;
use App\Services\Documents\SignedPdfStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\Permission\PermissionRegistrar;

#[Tries(3)]
#[Backoff([10, 30, 60])]
final class StoreSignedContractPdf implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly RendersContractPdf $pdf,
        private readonly PermissionRegistrar $registrar,
        private readonly SignedPdfStore $store,
    ) {}

    public function handle(ContractSigned $event): void
    {
        // `Media::creating` throws without a bound tenant, and queue workers start with none.
        $previousTenant = app()->bound('current_tenant_id') ? current_tenant_id() : null;
        $previousTeam = $this->registrar->getPermissionsTeamId();
        app()->instance('current_tenant_id', $event->tenantId);
        $this->registrar->setPermissionsTeamId($event->tenantId);

        try {
            $this->store($event);
        } finally {
            if ($previousTenant === null) {
                app()->forgetInstance('current_tenant_id');
            } else {
                app()->instance('current_tenant_id', $previousTenant);
            }

            $this->registrar->setPermissionsTeamId($previousTeam);
        }
    }

    private function store(ContractSigned $event): void
    {
        /** @var Contract $contract */
        $contract = Contract::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $event->tenantId)
            ->with('document')
            ->findOrFail($event->contractId);

        $document = $contract->document;

        if ($document === null || $document->getFirstMedia(SignedPdfStore::COLLECTION) !== null) {
            return;
        }

        $this->store->store($document, $this->pdf->renderFresh($contract), 'contract-'.$contract->id.'.pdf');
    }
}
