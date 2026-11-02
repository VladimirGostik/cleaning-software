<?php

declare(strict_types=1);

namespace App\Services\Expenses;

use App\Data\Expenses\ExpenseImportData;
use App\Data\Expenses\ExpenseImportPreviewData;
use App\Data\Expenses\ExpenseImportRowInputData;
use App\Data\Expenses\ExpenseStatementImportResultData;
use App\Data\Expenses\ExpenseStatementPreviewData;
use App\Data\Expenses\ExpenseStatementPreviewRowData;
use App\Data\Expenses\ExpenseStatementRowsData;
use App\Enums\ActionChannelEnum;
use App\Enums\ExpenseImportAccountStatusEnum;
use App\Enums\ExpenseImportRowStateEnum;
use App\Models\Expense;
use App\Models\User;
use App\Services\Ai\Guard\PromptGuard;
use App\Services\ExpenseImportService;
use Illuminate\Validation\ValidationException;

final readonly class BankExpenseRowsService
{
    public function __construct(
        private ExpenseImportService $imports,
        private PromptGuard $promptGuard,
    ) {}

    public function preview(ExpenseStatementRowsData $data, User $actor): ExpenseStatementPreviewData
    {
        [$statement, $withheld] = $this->statementOf($data, $actor);
        $inspected = $this->imports->inspect($statement, $actor, false);
        $matched = $inspected->account_check->status === ExpenseImportAccountStatusEnum::Matched;

        $counts = [
            ExpenseImportRowStateEnum::New->value => 0,
            ExpenseImportRowStateEnum::AlreadyImported->value => 0,
            ExpenseImportRowStateEnum::PreviouslyDeleted->value => 0,
            ExpenseImportRowStateEnum::PossibleMatch->value => 0,
        ];
        $rows = [];

        if ($matched) {
            $differs = $this->differing($inspected, $statement);

            foreach ($inspected->rows as $row) {
                $counts[$row->state->value]++;
                $rows[] = new ExpenseStatementPreviewRowData(
                    $row->reference,
                    $row->date,
                    $row->amount,
                    $row->state,
                    $row->existing_expense_id,
                    $row->include,
                    in_array($row->reference, $withheld, true),
                    isset($differs[$row->reference]),
                );
            }
        }

        return new ExpenseStatementPreviewData(
            $inspected->account_check,
            $counts,
            $matched ? $this->total($statement) : '0.00',
            $rows,
            $this->notice($withheld),
        );
    }

    public function import(ExpenseStatementRowsData $data, User $actor): ExpenseStatementImportResultData
    {
        [$statement, $withheld] = $this->statementOf($data, $actor);
        $inspected = $this->imports->inspect($statement, $actor, false);

        if ($inspected->account_check->status !== ExpenseImportAccountStatusEnum::Matched) {
            throw ValidationException::withMessages(['account_iban' => [__('app.expense_import_account_mismatch')]]);
        }

        $states = [];

        foreach ($inspected->rows as $row) {
            $states[$row->reference] = $row->state;
        }

        $this->assertKnown($data->include_references, $states, 'include_references');
        $this->assertKnown($data->exclude_references, $states, 'exclude_references');

        $rows = array_map(static fn (StatementRow $row): ExpenseImportRowInputData => new ExpenseImportRowInputData(
            reference: $row->reference,
            date: $row->date,
            amount: $row->amount,
            counterparty: $row->counterparty,
            note: $row->note,
            proposed_category: null,
            confidence: 0.0,
            include: ! in_array($row->reference, $data->exclude_references, true)
                && $states[$row->reference] !== ExpenseImportRowStateEnum::AlreadyImported
                && ($states[$row->reference] === ExpenseImportRowStateEnum::New || in_array($row->reference, $data->include_references, true)),
        ), $statement->debitRows);

        $result = $this->imports->import(new ExpenseImportData($statement->iban, $rows), $actor);

        return new ExpenseStatementImportResultData(
            $result->created,
            $result->already_imported,
            $result->excluded,
            $withheld,
            $this->notice($withheld),
        );
    }

    /**
     * @return array{0: CamtStatement, 1: list<string>}
     */
    private function statementOf(ExpenseStatementRowsData $data, User $actor): array
    {
        $rows = [];
        $withheld = [];

        foreach ($data->rows as $position => $row) {
            $verdict = $this->promptGuard->scan(trim($row->counterparty."\n".$row->note));
            $flagged = $verdict->flagged;

            if ($flagged) {
                $this->promptGuard->record($actor, 'bank_statement_row', current_tenant_id(), ActionChannelEnum::Mcp, $verdict);
                $withheld[] = $row->reference;
            }

            $rows[] = new StatementRow(
                $position + 1,
                $row->date,
                number_format((float) $row->amount, 2, '.', ''),
                $flagged ? null : $row->counterparty,
                $flagged ? null : $row->note,
                $row->reference,
            );
        }

        return [new CamtStatement(CamtStatementParser::normalizeIban($data->account_iban), $rows, 0, 0), $withheld];
    }

    /** @param  list<string>  $withheld */
    private function notice(array $withheld): ?string
    {
        $text = __('app.expense_bank_rows_text_withheld');

        return $withheld === [] || ! is_string($text) ? null : $text;
    }

    private function total(CamtStatement $statement): string
    {
        $sum = '0.00';

        foreach ($statement->debitRows as $row) {
            /** @var numeric-string $amount */
            $amount = $row->amount;
            $sum = bcadd($sum, $amount, 2);
        }

        return $sum;
    }

    /** @return array<string, true> */
    private function differing(ExpenseImportPreviewData $inspected, CamtStatement $statement): array
    {
        $sent = [];

        foreach ($statement->debitRows as $row) {
            $sent[$row->reference] = $row;
        }

        $ids = [];

        foreach ($inspected->rows as $row) {
            if ($row->state === ExpenseImportRowStateEnum::AlreadyImported && $row->existing_expense_id !== null) {
                $ids[$row->existing_expense_id] = $row->reference;
            }
        }

        $differs = [];

        foreach (Expense::query()->whereIn('id', array_keys($ids))->get(['id', 'amount', 'date']) as $expense) {
            $row = $sent[$ids[$expense->id]];

            /** @var numeric-string $stored */
            $stored = (string) $expense->amount;
            /** @var numeric-string $sentAmount */
            $sentAmount = $row->amount;

            if (bccomp($stored, $sentAmount, 2) !== 0 || $expense->date->toDateString() !== $row->date) {
                $differs[$row->reference] = true;
            }
        }

        return $differs;
    }

    /**
     * @param  list<string>  $references
     * @param  array<string, ExpenseImportRowStateEnum>  $states
     */
    private function assertKnown(array $references, array $states, string $field): void
    {
        foreach ($references as $position => $reference) {
            if (! isset($states[$reference])) {
                throw ValidationException::withMessages([$field => [__('app.expense_bank_rows_unknown_reference', ['position' => $position + 1])]]);
            }
        }
    }
}
