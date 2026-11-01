<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Ai\AnalyzesExpenses;
use App\Data\Expenses\BankCsvImportData;
use App\Data\Expenses\BankCsvMappingData;
use App\Data\Expenses\ExpenseAnalysisContextData;
use App\Data\Expenses\ExpenseCategoryProposalData;
use App\Data\Expenses\ExpenseCompanyContextData;
use App\Data\Expenses\ExpenseImportPreviewData;
use App\Data\Expenses\ExpenseImportResultData;
use App\Data\Expenses\ExpenseImportRowData;
use App\Data\Expenses\ExpenseRowData;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use App\Exceptions\AiUnavailableException;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\User;
use App\Services\Expenses\BankStatementParser;
use App\Services\Expenses\ParsedStatement;
use App\Services\Expenses\StatementRow;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final readonly class ExpenseImportService
{
    public function __construct(
        private DatabaseManager $db,
        private AnalyzesExpenses $analyzer,
        private BankStatementParser $parser,
    ) {}

    public function preview(UploadedFile $file): ExpenseImportPreviewData
    {
        $table = $this->parser->parse($file->getContent());
        $mapping = $this->resolveMapping($table);
        $extraction = $this->parser->extract($table, $mapping);

        if ($extraction->debitRows === [] && $extraction->skippedCreditRows > 0) {
            throw ValidationException::withMessages(['file' => [__('app.csv_no_debit_rows')]]);
        }

        if (count($extraction->debitRows) > $this->maxRows()) {
            throw ValidationException::withMessages(['file' => [__('app.csv_too_many_rows', ['max' => $this->maxRows()])]]);
        }

        $hashes = $this->parser->hashes($extraction->debitRows);
        $known = $this->knownHashes($hashes);
        $proposals = $this->proposals($extraction->debitRows, $hashes, $known);

        $rows = [];

        foreach ($extraction->debitRows as $position => $row) {
            $duplicate = isset($known[$hashes[$position]]);
            $proposal = $proposals[$row->row] ?? null;

            $rows[] = new ExpenseImportRowData(
                row: $row->row,
                date: $row->date,
                amount: $row->amount,
                counterparty: $row->counterparty,
                note: $row->note,
                reference: $row->reference,
                proposed_category: $proposal?->category,
                confidence: $proposal->confidence ?? 0.0,
                duplicate: $duplicate,
                include: ! $duplicate,
            );
        }

        return new ExpenseImportPreviewData($mapping, $rows, $extraction->skippedCreditRows);
    }

    public function import(BankCsvImportData $data, User $actor): ExpenseImportResultData
    {
        $rows = array_map($this->cleaned(...), $data->rows);

        try {
            $result = $this->persist($rows, $actor);
        } catch (UniqueConstraintViolationException) {
            $result = $this->persist($rows, $actor);
        }

        TenantInterface::query()->updateOrCreate(
            ['tenant_id' => current_tenant_id()],
            ['bank_csv_mapping' => $data->mapping->toArray()],
        );

        return $result;
    }

    /** @param  list<ExpenseImportRowData>  $rows */
    private function persist(array $rows, User $actor): ExpenseImportResultData
    {
        // Ordinals count repeats within the posted rows, so a client must post the whole preview and flag exclusions via `include`.
        $hashes = $this->parser->hashes($rows);

        return $this->db->transaction(function () use ($rows, $hashes, $actor): ExpenseImportResultData {
            $known = $this->knownHashes($hashes);
            $created = 0;
            $skipped = 0;

            foreach ($rows as $position => $row) {
                if (! $row->include) {
                    continue;
                }

                if (isset($known[$hashes[$position]])) {
                    $skipped++;

                    continue;
                }

                Expense::create([
                    'tenant_id' => current_tenant_id(),
                    'created_by_user_id' => $actor->id,
                    'status' => ExpenseStatusEnum::Draft,
                    'date' => $row->date,
                    'amount' => $row->amount,
                    'supplier_name' => $row->counterparty,
                    'description' => $row->note,
                    'bank_reference' => $row->reference,
                    'source' => ExpenseSourceEnum::BankCsv,
                    'external_hash' => $hashes[$position],
                    'ai_proposal' => $row->proposed_category === null ? null : [
                        'category' => $row->proposed_category->value,
                        'tenant_id' => current_tenant_id(),
                        'confidence' => $row->confidence,
                        'reason' => null,
                        'model' => config('ai.model'),
                    ],
                ]);
                $created++;
            }

            return new ExpenseImportResultData($created, $skipped);
        });
    }

    private function cleaned(ExpenseImportRowData $row): ExpenseImportRowData
    {
        return new ExpenseImportRowData(
            row: $row->row,
            date: $row->date,
            amount: number_format((float) $row->amount, 2, '.', ''),
            counterparty: $this->parser->cleanText($row->counterparty, 200),
            note: $this->parser->cleanText($row->note, 500),
            reference: $this->parser->cleanText($row->reference, 100),
            proposed_category: $row->proposed_category,
            confidence: $row->confidence,
            duplicate: $row->duplicate,
            include: $row->include,
        );
    }

    private function resolveMapping(ParsedStatement $table): BankCsvMappingData
    {
        $interface = TenantInterface::query()->where('tenant_id', current_tenant_id())->first();
        $stored = $interface?->bank_csv_mapping;

        if ($stored !== null) {
            $mapping = $this->validMapping($stored);

            if ($mapping !== null && $this->parser->fits($table, $mapping)) {
                return $mapping;
            }
        }

        try {
            $proposed = $this->analyzer->mapBankCsv($table->header, $this->sampleRows($table));
        } catch (AiUnavailableException) {
            throw ValidationException::withMessages(['file' => [__('app.ai_unavailable')]]);
        }

        $mapping = $this->validMapping([...$proposed->toArray(), 'delimiter' => $table->delimiter]);

        if ($mapping === null || ! $this->parser->fits($table, $mapping)) {
            throw ValidationException::withMessages(['file' => [__('app.csv_mapping_failed')]]);
        }

        return $mapping;
    }

    /** @param  array<mixed>  $payload */
    private function validMapping(array $payload): ?BankCsvMappingData
    {
        try {
            return BankCsvMappingData::validateAndCreate($payload);
        } catch (ValidationException) {
            return null;
        }
    }

    /** @return list<list<string>> */
    private function sampleRows(ParsedStatement $table): array
    {
        return array_map(
            static fn (array $cells): array => array_map(static fn (string $cell): string => mb_substr($cell, 0, 80), $cells),
            array_slice($table->records, 0, 3),
        );
    }

    /**
     * Duplicates are not sent to the provider; a provider failure only drops the suggestions.
     *
     * @param  list<StatementRow>  $rows
     * @param  list<string>  $hashes
     * @param  array<string, true>  $known
     * @return array<int, ExpenseCategoryProposalData>
     */
    private function proposals(array $rows, array $hashes, array $known): array
    {
        $pending = [];

        foreach ($rows as $position => $row) {
            if (! isset($known[$hashes[$position]])) {
                $pending[] = new ExpenseRowData($row->row, $row->amount, $row->counterparty, $row->note);
            }
        }

        $byRow = [];

        foreach (array_chunk($pending, max(1, config()->integer('ai.categorize_batch', 50))) as $batch) {
            try {
                foreach ($this->analyzer->categorize($batch, $this->context()) as $proposal) {
                    $byRow[$proposal->row] = $proposal;
                }
            } catch (AiUnavailableException) {
                break;
            }
        }

        return $byRow;
    }

    private function context(): ExpenseAnalysisContextData
    {
        $tenant = Tenant::query()->findOrFail(current_tenant_id());

        return new ExpenseAnalysisContextData(
            companies: [new ExpenseCompanyContextData($tenant->id, $tenant->name, $tenant->ico, $tenant->iban)],
            today: now()->toDateString(),
        );
    }

    /**
     * @param  list<string>  $hashes
     * @return array<string, true>
     */
    private function knownHashes(array $hashes): array
    {
        $known = [];

        foreach (Expense::query()->whereIn('external_hash', $hashes)->pluck('external_hash') as $hash) {
            if (is_string($hash)) {
                $known[$hash] = true;
            }
        }

        return $known;
    }

    private function maxRows(): int
    {
        return config()->integer('ai.csv_preview_max_rows', 100);
    }
}
