<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Invoice;
use App\Services\Efa\EInvoiceSubmissionResult;

interface SubmitsEInvoices
{
    public function submit(Invoice $invoice, string $xml): EInvoiceSubmissionResult;
}
