<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Invoice;

interface GeneratesPaymentQr
{
    /** Returns a base64 PNG data URI for Pay by Square QR code, or null when conditions unmet. */
    public function dataUri(Invoice $invoice): ?string;
}
