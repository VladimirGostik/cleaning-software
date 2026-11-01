<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\RendersContractPdf;
use App\Enums\DocumentTypeEnum;
use App\Models\Contract;
use App\Models\Tenant;
use App\Services\Documents\ContractTextService;
use App\Services\Documents\DocumentPdfRenderer;
use App\Services\Documents\SignedPdfStore;

final readonly class ContractPdfService implements RendersContractPdf
{
    public function __construct(
        private ContractTextService $text,
        private DocumentPdfRenderer $pdf,
        private SignedPdfStore $signed,
    ) {}

    public function render(Contract $contract): string
    {
        $contract->loadMissing('document');

        $stored = $contract->document?->getFirstMedia(SignedPdfStore::COLLECTION);

        return $stored !== null ? $this->signed->read($stored) : $this->renderFresh($contract);
    }

    /** Not `current_tenant_id()` — rendering must also work from queue/console callers with no bound tenant. */
    public function renderFresh(Contract $contract): string
    {
        $contract->loadMissing('document');
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($contract->tenant_id);

        return $this->pdf->render(
            $contract->title,
            $this->text->displayBody($contract) ?? '',
            $tenant,
            $contract->document->type ?? DocumentTypeEnum::fromCategory($contract->category),
            $contract->document->show_header ?? true,
            $contract->document->show_footer ?? true,
        );
    }
}
