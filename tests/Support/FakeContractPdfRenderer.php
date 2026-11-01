<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\RendersContractPdf;
use App\Models\Contract;
use App\Services\Documents\SignedPdfStore;

final class FakeContractPdfRenderer implements RendersContractPdf
{
    public int $freshRenders = 0;

    public function __construct(private readonly SignedPdfStore $signed) {}

    public function render(Contract $contract): string
    {
        $contract->loadMissing('document');
        $stored = $contract->document?->getFirstMedia('pdf');

        return $stored !== null ? $this->signed->read($stored) : $this->renderFresh($contract);
    }

    public function renderFresh(Contract $contract): string
    {
        $this->freshRenders++;

        return '%PDF-fake '.($contract->document?->body ?? '');
    }
}
