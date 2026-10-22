<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MergesPdfDocuments;
use App\Contracts\RendersInvoicePdf;
use App\Data\Invoices\BulkActionResultData;
use App\Data\Invoices\BulkSkippedItemData;
use App\Data\Invoices\InvoiceIssueData;
use App\Data\Invoices\InvoicePaymentData;
use App\Enums\BulkActionEnum;
use App\Enums\BulkSkipReasonEnum;
use App\Models\Invoice;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

final readonly class InvoiceBulkService
{
    public function __construct(
        private InvoiceService $invoices,
        private RendersInvoicePdf $pdf,
        private MergesPdfDocuments $merger,
    ) {}

    /** @param list<string> $ids */
    public function pay(array $ids, string $paidOn, ?User $actor): BulkActionResultData
    {
        return $this->run(BulkActionEnum::Pay, $ids, BulkSkipReasonEnum::NotPayable, function (Invoice $invoice) use ($paidOn, $actor): void {
            $this->invoices->recordPayment(
                $invoice,
                new InvoicePaymentData(
                    paid_on: $paidOn,
                    amount: abs($invoice->balance_due),
                    payment_type: $invoice->payment_type,
                ),
                $actor,
            );
        });
    }

    /** @param list<string> $ids */
    public function send(array $ids): BulkActionResultData
    {
        return $this->run(BulkActionEnum::Send, $ids, BulkSkipReasonEnum::NotIssued, fn (Invoice $invoice) => $this->invoices->send($invoice));
    }

    /** @param list<string> $ids */
    public function issue(array $ids, ?User $actor): BulkActionResultData
    {
        return $this->run(BulkActionEnum::Issue, $ids, BulkSkipReasonEnum::NotDraft, fn (Invoice $invoice) => $this->invoices->issue($invoice, new InvoiceIssueData(number: null), $actor));
    }

    /** @param list<string> $ids */
    public function mergedPdf(array $ids): string
    {
        $found = $this->load($ids);

        $invoices = array_map(
            fn (string $id): Invoice => $found->get($id) ?? throw (new ModelNotFoundException)->setModel(Invoice::class, [$id]),
            $ids,
        );

        $binaries = array_map(fn (Invoice $invoice): string => $this->pdf->render($invoice), $invoices);

        return count($binaries) === 1 ? $binaries[0] : $this->merger->merge($binaries);
    }

    /**
     * @param  list<string>  $ids
     * @param  Closure(Invoice): mixed  $handler
     */
    private function run(BulkActionEnum $action, array $ids, BulkSkipReasonEnum $statusReason, Closure $handler): BulkActionResultData
    {
        $found = $this->load($ids);
        $processed = 0;
        $skipped = [];

        foreach ($ids as $id) {
            $invoice = $found->get($id);

            if ($invoice === null) {
                $skipped[] = new BulkSkippedItemData($id, null, BulkSkipReasonEnum::NotFound);

                continue;
            }

            try {
                $handler($invoice);
                $processed++;
            } catch (ValidationException $e) {
                $skipped[] = new BulkSkippedItemData($id, $invoice->number, $this->reasonFor($e, $statusReason));
            }
        }

        return new BulkActionResultData($action, $processed, $skipped);
    }

    private function reasonFor(ValidationException $e, BulkSkipReasonEnum $statusReason): BulkSkipReasonEnum
    {
        $errors = $e->errors();

        return match (true) {
            isset($errors['customer_email']) => BulkSkipReasonEnum::NoEmail,
            isset($errors['supplier']) => BulkSkipReasonEnum::SupplierIncomplete,
            isset($errors['number']) => BulkSkipReasonEnum::NumberTaken,
            isset($errors['amount']) => BulkSkipReasonEnum::NotPayable,
            default => $statusReason,
        };
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<string, Invoice>
     */
    private function load(array $ids): Collection
    {
        /** @var Collection<string, Invoice> */
        return Invoice::query()->with('items')->whereIn('id', $ids)->get()->keyBy('id');
    }
}
