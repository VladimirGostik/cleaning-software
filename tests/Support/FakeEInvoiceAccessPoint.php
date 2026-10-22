<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\SubmitsEInvoices;
use App\Models\Invoice;
use App\Services\Efa\EInvoiceSubmissionResult;
use Closure;

final class FakeEInvoiceAccessPoint implements SubmitsEInvoices
{
    /** @param Closure(Invoice, string): EInvoiceSubmissionResult $handler */
    public function __construct(public Closure $handler) {}

    public function submit(Invoice $invoice, string $xml): EInvoiceSubmissionResult
    {
        return ($this->handler)($invoice, $xml);
    }
}
