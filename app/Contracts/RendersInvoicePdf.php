<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Invoice;

interface RendersInvoicePdf
{
    public function render(Invoice $invoice): string;
}
