<?php

declare(strict_types=1);

namespace App\Services\Efa;

use App\Contracts\SubmitsEInvoices;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

final readonly class NullEInvoiceAccessPoint implements SubmitsEInvoices
{
    public function submit(Invoice $invoice, string $xml): EInvoiceSubmissionResult
    {
        throw ValidationException::withMessages(['efa' => [__('app.efa_not_configured')]]);
    }
}
