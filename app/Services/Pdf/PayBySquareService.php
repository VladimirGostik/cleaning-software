<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Contracts\GeneratesPaymentQr;
use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use Engazan\PayBySquare\Exception\PayBySquareException;
use Engazan\PayBySquare\Exception\ValidationException;
use Engazan\PayBySquare\Generator;
use Illuminate\Support\Facades\Log;

final readonly class PayBySquareService implements GeneratesPaymentQr
{
    public function dataUri(Invoice $invoice): ?string
    {
        if ($invoice->status !== InvoiceStatusEnum::Issued && $invoice->status !== InvoiceStatusEnum::Overdue) {
            return null;
        }

        if (empty($invoice->supplier_iban)) {
            return null;
        }

        // Pay-by-Square is a SK EUR-domestic standard — non-EUR invoices skip QR.
        if ($invoice->currency !== CurrencyEnum::EUR) {
            return null;
        }

        $amount = $invoice->amount_payable;

        if ($amount <= 0 || $invoice->isSettled()) {
            return null;
        }

        try {
            $generator = (new Generator)
                ->setIban($invoice->supplier_iban)
                ->setAmount($amount)
                ->setCurrency($invoice->currency->value)
                ->setRecipient($invoice->supplier_name ?? '');

            if (! empty($invoice->variable_symbol)) {
                $generator->setVariableSymbol($invoice->variable_symbol);
            }

            $generator->setDueDate($invoice->due_date->toDateTime());

            if (! empty($invoice->supplier_swift)) {
                $generator->setSwift($invoice->supplier_swift);
            }

            return $generator->getDataUri(300);
        } catch (ValidationException|PayBySquareException $e) {
            Log::warning('pay_by_square.generation_failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
