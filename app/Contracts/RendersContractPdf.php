<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Contract;

interface RendersContractPdf
{
    /** Returns raw PDF bytes for the given contract. */
    public function render(Contract $contract): string;
}
