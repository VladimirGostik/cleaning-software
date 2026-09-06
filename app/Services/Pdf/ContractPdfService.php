<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\RendersContractPdf;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Spatie\LaravelPdf\Facades\Pdf;

final readonly class ContractPdfService implements RendersContractPdf
{
    /** Returns raw PDF bytes via the `chrome` driver. */
    public function render(Contract $contract): string
    {
        $contract->loadMissing(['employmentContract', 'contractable']);

        if ($contract->contractable instanceof TenantMembership) {
            $contract->contractable->loadMissing('user');
        }

        // Not `current_tenant_id()` — rendering must also work from queue/console callers with no bound tenant.
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($contract->tenant_id);

        return Pdf::view('pdf.contracts.default', ['contract' => $contract, 'tenant' => $tenant])
            ->format('A4')
            ->portrait()
            ->generatePdfContent();
    }
}
