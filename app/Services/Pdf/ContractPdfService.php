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
    public function render(Contract $contract): string
    {
        $contract = $this->eagerLoadForRender($contract);

        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail(app('current_tenant_id'));

        return Pdf::view('pdf.contracts.default', ['contract' => $contract, 'tenant' => $tenant])
            ->format('A4')
            ->portrait()
            ->generatePdfContent();
    }

    /**
     * Eagerly load all relations required by the PDF Blade template,
     * including the nested `user` relation on TenantMembership contractables.
     */
    public function eagerLoadForRender(Contract $contract): Contract
    {
        $contract->loadMissing(['employmentContract', 'contractTemplate', 'contractable']);

        if ($contract->contractable instanceof TenantMembership) {
            $contract->contractable->loadMissing('user');
        }

        return $contract;
    }
}
