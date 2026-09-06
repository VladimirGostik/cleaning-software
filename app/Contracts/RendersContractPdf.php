<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Contract;

interface RendersContractPdf
{
    public function render(Contract $contract): string;
}
