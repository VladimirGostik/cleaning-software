<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Invoice;

interface RendersInvoicePdf
{
    /** Returns raw PDF bytes for the given invoice. */
    public function render(Invoice $invoice): string;
}
