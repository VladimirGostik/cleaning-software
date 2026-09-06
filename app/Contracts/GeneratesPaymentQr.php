<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Invoice;

interface GeneratesPaymentQr
{
    public function dataUri(Invoice $invoice): ?string;
}
