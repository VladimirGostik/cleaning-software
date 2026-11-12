<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invoices\InvoicePaymentData;
use App\Data\Invoices\PaymentImportData;
use App\Data\Invoices\PaymentImportFileData;
use App\Data\Invoices\PaymentImportInvoiceData;
use App\Data\Invoices\PaymentImportInvoiceLinkData;
use App\Data\Invoices\PaymentImportInvoiceSearchData;
use App\Data\Invoices\PaymentImportOverpaymentData;
use App\Data\Invoices\PaymentImportPreviewData;
use App\Data\Invoices\PaymentImportResultData;
use App\Data\Invoices\PaymentImportRowData;
use App\Data\Invoices\PaymentImportRowInputData;
use App\Data\Invoices\PaymentImportSkippedRowData;
use App\Enums\CurrencyEnum;
use App\Enums\ExpenseImportAccountStatusEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentImportRowStateEnum;
use App\Enums\PaymentSourceEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\Expenses\CamtStatementParser;
use App\Services\Expenses\StatementAccountCheck;
use App\Services\PaymentImport\PaymentImportAnalysis;
use App\Services\PaymentImport\PaymentImportEntry;
use App\Support\ActivityBatch;
use App\Support\SafeText;
use App\Utils\Filters;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class InvoicePaymentImportService
{
    private const int MAX_ATTEMPTS = 3;

    private const int DUPLICATE_WINDOW_DAYS = 7;

    private const int SEARCH_LIMIT = 20;

    private const int NOTE_PART_MAX = 120;

    public function __construct(
        private DatabaseManager $db,
        private CamtStatementParser $parser,
        private StatementAccountCheck $accountCheck,
        private InvoiceService $invoices,
    ) {}

    /** @param  list<UploadedFile>  $files */
    public function preview(array $files, User $actor): PaymentImportPreviewData
    {
        $analysis = $this->analyse($files, $actor);
        $entries = $this->ordered($analysis->entries);
        [$live, $trashed] = $this->knownPayments(array_map(static fn (PaymentImportEntry $entry): string => $entry->key, $entries));
        $byVs = $this->invoicesByVs($entries);
        $payments = $this->paymentsOf($byVs);

        $positions = [];
        $counts = [];

        foreach ($entries as $entry) {
            $invoice = $this->uniqueMatch($entry, $byVs);

            if ($invoice !== null) {
                $counts[$invoice->id] = ($counts[$invoice->id] ?? 0) + 1;
            }
        }

        $running = [];
        $rows = [];

        foreach ($entries as $entry) {
            $row = $entry->row;
            $cents = $entry->cents();
            $invoice = $this->uniqueMatch($entry, $byVs);
            $existing = $live[$entry->key] ?? null;
            $before = null;
            $recordable = false;

            if ($existing !== null) {
                $state = PaymentImportRowStateEnum::AlreadyImported;
            } elseif ($row->variableSymbol === null) {
                $state = PaymentImportRowStateEnum::NoVs;
            } elseif ($invoice === null) {
                $state = PaymentImportRowStateEnum::VsNotFound;
            } else {
                $running[$invoice->id] ??= $this->cents($invoice->balance_due);
                $before = $running[$invoice->id];
                $blocked = $this->blockedState($invoice, $before);

                if ($blocked === PaymentImportRowStateEnum::InvoiceNotPayable) {
                    $state = $blocked;
                    $before = null;
                } elseif ($blocked !== null) {
                    $state = $blocked;
                    $before = max(0, $before);
                } elseif (isset($trashed[$entry->key])) {
                    $state = PaymentImportRowStateEnum::PreviouslyDeleted;
                    $recordable = true;
                } elseif (($duplicate = $this->duplicateOf($entry, $payments[$invoice->id] ?? [])) !== null) {
                    $state = PaymentImportRowStateEnum::PossibleDuplicate;
                    $existing = $duplicate;
                    $recordable = true;
                } else {
                    $state = match (true) {
                        $cents > $before => PaymentImportRowStateEnum::Overpaid,
                        $cents < $before => PaymentImportRowStateEnum::Partial,
                        default => PaymentImportRowStateEnum::Matched,
                    };
                    $recordable = true;
                }
            }

            $include = in_array($state, [PaymentImportRowStateEnum::Partial, PaymentImportRowStateEnum::Matched], true);

            if ($include && $invoice !== null) {
                $running[$invoice->id] -= $cents;
            }

            $position = null;

            if ($invoice !== null && ($counts[$invoice->id] ?? 0) > 1) {
                $positions[$invoice->id] = ($positions[$invoice->id] ?? 0) + 1;
                $position = $positions[$invoice->id];
            }

            $rows[] = new PaymentImportRowData(
                reference: $row->reference,
                file_index: $entry->fileIndex,
                date: $row->date,
                amount: $row->amount,
                payer_name: $row->counterparty,
                payer_iban: $row->counterpartyIban,
                variable_symbol: $row->variableSymbol,
                note: $row->note,
                state: $state,
                invoice: $invoice === null ? null : PaymentImportInvoiceData::fromInvoice($invoice),
                balance_before: $before === null ? null : $this->money($before),
                amount_to_record: $recordable && $before !== null ? $this->money(min($cents, $before)) : null,
                excess: $recordable && $before !== null ? $this->money(max(0, $cents - $before)) : null,
                instalment_position: $position,
                instalment_count: $position === null || $invoice === null ? null : $counts[$invoice->id],
                existing_payment_id: $existing,
                include: $include,
            );
        }

        return new PaymentImportPreviewData(
            $analysis->files,
            $rows,
            $analysis->ignoredDebitRows,
            $analysis->unreadableCreditRows,
            $analysis->overlappingRows,
        );
    }

    public function import(PaymentImportData $data, User $actor): PaymentImportResultData
    {
        $analysis = $this->analyse($data->files, $actor);

        if (! $analysis->hasMatchedFile) {
            throw ValidationException::withMessages(['files' => [__('app.payment_import_no_matching_file')]]);
        }

        $entries = $this->ordered($analysis->entries);
        $known = [];

        foreach ($entries as $entry) {
            $known[$entry->row->reference] = true;
        }

        $decisions = [];

        foreach ($data->rows as $row) {
            if (! isset($known[$row->reference])) {
                throw ValidationException::withMessages(['rows' => [__('app.payment_import_unknown_row', ['reference' => $row->reference])]]);
            }

            $decisions[$row->reference] = $row;
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return ActivityBatch::run(fn (): PaymentImportResultData => $this->db->transaction(fn (): PaymentImportResultData => $this->record($entries, $decisions, $actor)));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $exclude
     */
    public function importServerMatches(array $files, array $exclude, User $actor): PaymentImportResultData
    {
        $preview = $this->preview($files, $actor);
        $this->assertAccountsMatch($preview);
        $skip = array_flip($exclude);

        $rows = array_map(
            static fn (PaymentImportRowData $row): PaymentImportRowInputData => new PaymentImportRowInputData($row->reference, true),
            array_values(array_filter($preview->rows, static fn (PaymentImportRowData $row): bool => $row->include && ! isset($skip[$row->reference]))),
        );

        return $this->import(new PaymentImportData($files, $rows), $actor);
    }

    public function assertAccountsMatch(PaymentImportPreviewData $preview): void
    {
        foreach ($preview->files as $file) {
            if ($file->account->status !== ExpenseImportAccountStatusEnum::Matched) {
                throw ValidationException::withMessages(['files.'.$file->index => [$file->name.': '.__('app.payment_import_no_matching_file')]]);
            }
        }
    }

    /** @return list<PaymentImportInvoiceData> */
    public function searchInvoices(PaymentImportInvoiceSearchData $data): array
    {
        $query = Invoice::query()
            ->whereIn('status', [InvoiceStatusEnum::Issued, InvoiceStatusEnum::Overdue])
            ->where('currency', CurrencyEnum::EUR)
            ->where('total', '>', 0);

        $search = trim((string) $data->search);

        if ($search !== '') {
            $query->where(static function (Builder $query) use ($search): void {
                Filters::whereContains($query, 'number', $search);
                Filters::whereContains($query, 'customer_name', $search, 'or');
                Filters::whereContains($query, 'variable_symbol', $search, 'or');
            });
        }

        if ($data->amount !== null) {
            $query->orderByRaw('case when round(total - deposit - paid_amount, 2) = ?::numeric then 0 else 1 end', [$data->amount]);
        }

        return array_values(
            $query
                ->orderBy('due_date')
                ->orderBy('number')
                ->limit(self::SEARCH_LIMIT)
                ->get()
                ->map(static fn (Invoice $invoice): PaymentImportInvoiceData => PaymentImportInvoiceData::fromInvoice($invoice))
                ->all(),
        );
    }

    /**
     * @param  list<PaymentImportEntry>  $entries
     * @param  array<string, PaymentImportRowInputData>  $decisions
     */
    private function record(array $entries, array $decisions, User $actor): PaymentImportResultData
    {
        [$live] = $this->knownPayments(array_map(static fn (PaymentImportEntry $entry): string => $entry->key, $entries));
        $byVs = $this->invoicesByVs($entries);

        $recorded = 0;
        $total = 0;
        $already = 0;
        $excluded = 0;
        $touched = [];
        $overpayments = [];
        $skipped = [];

        foreach ($entries as $entry) {
            $row = $entry->row;

            if (isset($live[$entry->key])) {
                $already++;

                continue;
            }

            $decision = $decisions[$row->reference] ?? null;

            if ($decision === null || ! $decision->include) {
                $excluded++;

                continue;
            }

            $invoice = $this->target($entry, $decision, $byVs);

            if (! $invoice instanceof Invoice) {
                $skipped[] = new PaymentImportSkippedRowData($row->reference, $invoice['state'], $invoice['number']);

                continue;
            }

            $pay = min($entry->cents(), $this->cents($invoice->balance_due));
            $excess = $entry->cents() - $pay;

            $this->invoices->recordPayment(
                $invoice,
                new InvoicePaymentData(
                    paid_on: $row->date,
                    amount: $pay / 100,
                    payment_type: PaymentTypeEnum::Transfer,
                    note: $this->note($entry, $excess),
                ),
                $actor,
                PaymentSourceEnum::BankImport,
                $entry->key,
            );

            $recorded++;
            $total += $pay;
            $touched[$invoice->id] = [
                'number' => (string) $invoice->number,
                'balance' => $this->money(max(0, $this->cents($invoice->balance_due))),
                'paid' => $invoice->status === InvoiceStatusEnum::Paid,
            ];

            if ($excess > 0) {
                $overpayments[] = new PaymentImportOverpaymentData($row->reference, $invoice->id, (string) $invoice->number, $row->counterparty, $this->money($excess));
            }
        }

        $settled = [];
        $partly = [];

        foreach ($touched as $id => $info) {
            $link = new PaymentImportInvoiceLinkData((string) $id, $info['number'], $info['balance']);

            if ($info['paid']) {
                $settled[] = $link;
            } else {
                $partly[] = $link;
            }
        }

        return new PaymentImportResultData($recorded, $this->money($total), $already, $excluded, $settled, $partly, $overpayments, $skipped);
    }

    /**
     * @param  array<string, list<Invoice>>  $byVs
     * @return Invoice|array{state: PaymentImportRowStateEnum, number: string|null}
     */
    private function target(PaymentImportEntry $entry, PaymentImportRowInputData $decision, array $byVs): Invoice|array
    {
        $id = $decision->invoice_id;

        if ($id === null) {
            if ($entry->row->variableSymbol === null) {
                return ['state' => PaymentImportRowStateEnum::NoVs, 'number' => null];
            }

            $candidates = $byVs[$entry->row->variableSymbol] ?? [];
            $id = count($candidates) === 1 ? $candidates[0]->id : null;
        }

        $invoice = $id === null ? null : Invoice::query()->lockForUpdate()->find($id);

        if ($invoice === null) {
            return ['state' => PaymentImportRowStateEnum::VsNotFound, 'number' => null];
        }

        $number = $invoice->number;

        $blocked = $this->blockedState($invoice, $this->cents($invoice->balance_due));

        return $blocked === null ? $invoice : ['state' => $blocked, 'number' => $number];
    }

    private function blockedState(Invoice $invoice, int $balance): ?PaymentImportRowStateEnum
    {
        if (! in_array($invoice->status, [InvoiceStatusEnum::Issued, InvoiceStatusEnum::Overdue, InvoiceStatusEnum::Paid], true)
            || (float) $invoice->total <= 0.0
            || $invoice->currency !== CurrencyEnum::EUR) {
            return PaymentImportRowStateEnum::InvoiceNotPayable;
        }

        return $invoice->status === InvoiceStatusEnum::Paid || $balance <= 0 ? PaymentImportRowStateEnum::InvoicePaid : null;
    }

    private function note(PaymentImportEntry $entry, int $excess): ?string
    {
        $note = __('app.payment_import_note', [
            'reference' => $entry->row->reference,
            'payer' => SafeText::clean($entry->row->counterparty, self::NOTE_PART_MAX) ?? '-',
            'text' => SafeText::clean($entry->row->note, self::NOTE_PART_MAX) ?? '-',
        ]);

        if ($excess > 0) {
            $note .= ' '.__('app.payment_import_note_excess', ['amount' => $this->money($excess)]);
        }

        return SafeText::clean($note, 500);
    }

    /** @param  list<UploadedFile>  $files */
    private function analyse(array $files, User $actor): PaymentImportAnalysis
    {
        $described = [];
        $entries = [];
        $seen = [];
        $matched = false;
        $ignored = 0;
        $unreadable = 0;
        $overlapping = 0;

        foreach ($files as $index => $file) {
            $name = $file->getClientOriginalName();

            try {
                $statement = $this->parser->parse($file->getContent());
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['files.'.$index => [$name.': '.$e->validator->errors()->first('file')]]);
            }

            $check = $this->accountCheck->check($statement->iban, $actor, PermissionEnum::EditInvoices);
            $described[] = new PaymentImportFileData($index, $name, $statement->iban, $statement->periodFrom, $statement->periodTo, count($statement->creditRows), $check);

            if ($check->status !== ExpenseImportAccountStatusEnum::Matched) {
                continue;
            }

            $matched = true;
            $ignored += count($statement->debitRows);
            $unreadable += $statement->skippedCreditRows - count($statement->creditRows);

            foreach ($statement->creditRows as $row) {
                $key = $this->parser->entryKey($statement->iban, $row->reference);
                $previous = $seen[$key] ?? null;

                if ($previous !== null) {
                    if ($previous->row->amount !== $row->amount || $previous->row->date !== $row->date) {
                        throw ValidationException::withMessages(['files' => [__('app.payment_import_conflicting_reference', ['reference' => $row->reference])]]);
                    }

                    $overlapping++;

                    continue;
                }

                $entries[] = $seen[$key] = new PaymentImportEntry($row, $index, $key);
            }
        }

        $max = config()->integer('invoicing.payment_import.max_rows');

        if (count($entries) > $max) {
            throw ValidationException::withMessages(['files' => [__('app.payment_import_too_many_rows', ['max' => $max])]]);
        }

        return new PaymentImportAnalysis($described, $entries, $matched, $ignored, $unreadable, $overlapping);
    }

    /**
     * @param  list<PaymentImportEntry>  $entries
     * @return list<PaymentImportEntry>
     */
    private function ordered(array $entries): array
    {
        usort($entries, static fn (PaymentImportEntry $a, PaymentImportEntry $b): int => [$a->row->date, $a->row->reference] <=> [$b->row->date, $b->row->reference]);

        return $entries;
    }

    /**
     * @param  list<string>  $keys
     * @return array{0: array<string, string>, 1: array<string, true>}
     */
    private function knownPayments(array $keys): array
    {
        $live = [];
        $trashed = [];

        foreach (array_chunk($keys, 1000) as $chunk) {
            $found = InvoicePayment::query()->withTrashed()->whereIn('external_reference', $chunk)->get(['id', 'external_reference', 'deleted_at']);

            foreach ($found as $payment) {
                if ($payment->external_reference === null) {
                    continue;
                }

                if ($payment->deleted_at === null) {
                    $live[$payment->external_reference] = $payment->id;
                } else {
                    $trashed[$payment->external_reference] = true;
                }
            }
        }

        return [$live, $trashed];
    }

    /**
     * @param  list<PaymentImportEntry>  $entries
     * @return array<string, list<Invoice>>
     */
    private function invoicesByVs(array $entries): array
    {
        $symbols = array_values(array_unique(array_filter(array_map(static fn (PaymentImportEntry $entry): ?string => $entry->row->variableSymbol, $entries))));
        $byVs = [];

        foreach (array_chunk($symbols, 1000) as $chunk) {
            $found = Invoice::query()
                ->whereIn(DB::raw("ltrim(variable_symbol, '0')"), $chunk)
                ->where('status', '!=', InvoiceStatusEnum::Draft)
                ->get();

            foreach ($found as $invoice) {
                $byVs[ltrim((string) $invoice->variable_symbol, '0')][] = $invoice;
            }
        }

        return $byVs;
    }

    /**
     * @param  array<string, list<Invoice>>  $byVs
     * @return array<string, list<InvoicePayment>>
     */
    private function paymentsOf(array $byVs): array
    {
        $ids = [];

        foreach ($byVs as $invoices) {
            foreach ($invoices as $invoice) {
                $ids[] = $invoice->id;
            }
        }

        $payments = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (InvoicePayment::query()->whereIn('invoice_id', $chunk)->get(['id', 'invoice_id', 'amount', 'paid_on', 'external_reference']) as $payment) {
                $payments[$payment->invoice_id][] = $payment;
            }
        }

        return $payments;
    }

    /** @param  array<string, list<Invoice>>  $byVs */
    private function uniqueMatch(PaymentImportEntry $entry, array $byVs): ?Invoice
    {
        $candidates = $entry->row->variableSymbol === null ? [] : ($byVs[$entry->row->variableSymbol] ?? []);

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /** @param  list<InvoicePayment>  $payments */
    private function duplicateOf(PaymentImportEntry $entry, array $payments): ?string
    {
        $date = Carbon::parse($entry->row->date);

        foreach ($payments as $payment) {
            if ($payment->external_reference === $entry->key || $this->cents(abs((float) $payment->amount)) !== $entry->cents()) {
                continue;
            }

            if ((int) abs($date->diffInDays($payment->paid_on, false)) <= self::DUPLICATE_WINDOW_DAYS) {
                return $payment->id;
            }
        }

        return null;
    }

    private function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
