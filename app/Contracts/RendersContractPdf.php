<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Contract;

interface RendersContractPdf
{
    /** Stored signed snapshot when present, otherwise a fresh render. */
    public function render(Contract $contract): string;

    public function renderFresh(Contract $contract): string;
}
