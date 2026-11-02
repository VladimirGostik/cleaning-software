<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Ai\AnalyzesExpenses;
use App\Data\Expenses\ExpenseAnalysisContextData;
use App\Data\Expenses\ExpenseCategoryProposalData;
use App\Data\Expenses\ExpenseCompanyContextData;
use App\Data\Expenses\ExpenseImportAccountCheckData;
use App\Data\Expenses\ExpenseImportData;
use App\Data\Expenses\ExpenseImportPreviewData;
use App\Data\Expenses\ExpenseImportResultData;
use App\Data\Expenses\ExpenseImportRowData;
use App\Data\Expenses\ExpenseImportRowInputData;
use App\Data\Expenses\ExpenseRowData;
use App\Enums\CurrencyEnum;
use App\Enums\ExpenseImportAccountStatusEnum;
use App\Enums\ExpenseImportRowStateEnum;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use App\Exceptions\AiUnavailableException;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\Guard\PromptGuard;
use App\Services\Expenses\CamtStatement;
use App\Services\Expenses\CamtStatementParser;
use App\Services\Expenses\StatementRow;
use App\Services\Tenancy\TenantPermissionSnapshot;
use App\Support\SafeText;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final readonly class ExpenseImportService
{
    private const int MATCH_WINDOW_DAYS = 3;

    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private DatabaseManager $db,
        private AnalyzesExpenses $analyzer,
        private CamtStatementParser $parser,
        private PromptGuard $promptGuard,
        private TenantPermissionSnapshot $snapshot,
    ) {}

    public function preview(UploadedFile $file, User $actor): ExpenseImportPreviewData
    {
        return $this->inspect($this->parser->parse($file->getContent()), $actor, true);
    }

    public function inspect(CamtStatement $statement, User $actor, bool $categorize): ExpenseImportPreviewData
    {
        $check = $this->accountCheck($statement->iban, $actor);

        if ($check->status !== ExpenseImportAccountStatusEnum::Matched) {
            return new ExpenseImportPreviewData($check, [], $statement->skippedCreditRows, $statement->skippedOtherRows);
        }

        $rows = $statement->debitRows;
        $keys = array_map(fn (StatementRow $row): string => $this->parser->entryKey($statement->iban, $row->reference), $rows);
        [$live, $trashed] = $this->knownExpenses($keys);

        $unmatched = [];
        $importable = 0;

        foreach ($rows as $position => $row) {
            if (isset($live[$keys[$position]])) {
                continue;
            }

            $importable++;

            if (! isset($trashed[$keys[$position]])) {
                $unmatched[] = $row;
            }
        }

        $max = config()->integer('expenses.statement.max_new_rows');

        if ($importable > $max) {
            throw ValidationException::withMessages(['file' => [__('app.expense_import_too_many_rows', ['max' => $max])]]);
        }

        $matches = $this->possibleMatches($unmatched, $keys);
        $fresh = array_values(array_filter($unmatched, static fn (StatementRow $row): bool => ! isset($matches[$row->reference])));
        $proposals = $categorize ? $this->proposals($fresh) : [];

        $data = [];

        foreach ($rows as $position => $row) {
            $key = $keys[$position];
            $state = match (true) {
                isset($live[$key]) => ExpenseImportRowStateEnum::AlreadyImported,
                isset($trashed[$key]) => ExpenseImportRowStateEnum::PreviouslyDeleted,
                isset($matches[$row->reference]) => ExpenseImportRowStateEnum::PossibleMatch,
                default => ExpenseImportRowStateEnum::New,
            };
            $proposal = $proposals[$row->row] ?? null;

            $data[] = new ExpenseImportRowData(
                reference: $row->reference,
                date: $row->date,
                amount: $row->amount,
                counterparty: $row->counterparty,
                note: $row->note,
                proposed_category: $proposal?->category,
                confidence: $proposal->confidence ?? 0.0,
                state: $state,
                existing_expense_id: $live[$key] ?? $matches[$row->reference] ?? null,
                include: $state === ExpenseImportRowStateEnum::New,
            );
        }

        return new ExpenseImportPreviewData($check, $data, $statement->skippedCreditRows, $statement->skippedOtherRows);
    }

    public function import(ExpenseImportData $data, User $actor): ExpenseImportResultData
    {
        $iban = CamtStatementParser::normalizeIban($data->account_iban);
        $firmIban = Tenant::query()->findOrFail(current_tenant_id())->iban;

        if ($firmIban === null || CamtStatementParser::normalizeIban($firmIban) !== $iban) {
            throw ValidationException::withMessages(['account_iban' => [__('app.expense_import_account_mismatch')]]);
        }

        $keys = array_map(fn (ExpenseImportRowInputData $row): string => $this->parser->entryKey($iban, $row->reference), $data->rows);

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->persist($data->rows, $keys, $actor);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param  list<ExpenseImportRowInputData>  $rows
     * @param  list<string>  $keys
     */
    private function persist(array $rows, array $keys, User $actor): ExpenseImportResultData
    {
        return $this->db->transaction(function () use ($rows, $keys, $actor): ExpenseImportResultData {
            [$live] = $this->knownExpenses($keys);

            $max = config()->integer('expenses.statement.max_new_rows');

            if (count($rows) - count($live) > $max) {
                throw ValidationException::withMessages(['rows' => [__('app.expense_import_too_many_rows', ['max' => $max])]]);
            }

            $created = 0;
            $imported = 0;
            $excluded = 0;

            $ordered = $keys;
            asort($ordered, SORT_STRING);

            foreach (array_keys($ordered) as $position) {
                $row = $rows[$position];

                if (isset($live[$keys[$position]])) {
                    $imported++;

                    continue;
                }

                if (! $row->include) {
                    $excluded++;

                    continue;
                }

                Expense::create([
                    'tenant_id' => current_tenant_id(),
                    'created_by_user_id' => $actor->id,
                    'status' => ExpenseStatusEnum::Draft,
                    'date' => $row->date,
                    'amount' => number_format((float) $row->amount, 2, '.', ''),
                    'currency' => CurrencyEnum::EUR,
                    'supplier_name' => SafeText::clean($row->counterparty, 200),
                    'description' => SafeText::clean($row->note, 500),
                    'bank_reference' => $row->reference,
                    'source' => ExpenseSourceEnum::BankStatement,
                    'external_hash' => $keys[$position],
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

            return new ExpenseImportResultData($created, $imported, $excluded);
        });
    }

    private function accountCheck(string $iban, User $actor): ExpenseImportAccountCheckData
    {
        $active = Tenant::query()->findOrFail(current_tenant_id());

        if ($this->sameIban($active->iban, $iban)) {
            return new ExpenseImportAccountCheckData(ExpenseImportAccountStatusEnum::Matched, $iban);
        }

        foreach ($this->snapshot->forRequest($actor) as $visibility) {
            if ($visibility->manageExpenses && $visibility->tenant->is_active && $this->sameIban($visibility->tenant->iban, $iban)) {
                return new ExpenseImportAccountCheckData(
                    ExpenseImportAccountStatusEnum::OtherCompany,
                    $iban,
                    $visibility->tenant->id,
                    $visibility->tenant->name,
                );
            }
        }

        $status = trim((string) $active->iban) === ''
            ? ExpenseImportAccountStatusEnum::CompanyIbanMissing
            : ExpenseImportAccountStatusEnum::UnknownAccount;

        return new ExpenseImportAccountCheckData($status, $iban);
    }

    private function sameIban(?string $firmIban, string $statementIban): bool
    {
        return $firmIban !== null && CamtStatementParser::normalizeIban($firmIban) === $statementIban;
    }

    /**
     * @param  list<string>  $keys
     * @return array{0: array<string, string>, 1: array<string, true>}
     */
    private function knownExpenses(array $keys): array
    {
        $live = [];
        $trashed = [];

        foreach (array_chunk($keys, 1000) as $chunk) {
            $found = Expense::query()->withTrashed()->whereIn('external_hash', $chunk)->get(['id', 'external_hash', 'deleted_at']);

            foreach ($found as $expense) {
                if ($expense->external_hash === null) {
                    continue;
                }

                if ($expense->deleted_at === null) {
                    $live[$expense->external_hash] = $expense->id;
                } else {
                    $trashed[$expense->external_hash] = true;
                }
            }
        }

        return [$live, $trashed];
    }

    /**
     * @param  list<StatementRow>  $rows
     * @param  list<string>  $fileKeys
     * @return array<string, string>
     */
    private function possibleMatches(array $rows, array $fileKeys): array
    {
        if ($rows === []) {
            return [];
        }

        $dates = array_map(static fn (StatementRow $row): string => $row->date, $rows);
        $from = Carbon::parse(min($dates))->subDays(self::MATCH_WINDOW_DAYS)->toDateString();
        $to = Carbon::parse(max($dates))->addDays(self::MATCH_WINDOW_DAYS)->toDateString();

        $candidates = Expense::query()
            ->where('currency', CurrencyEnum::EUR->value)
            ->whereBetween('date', [$from, $to])
            ->whereIn('amount', array_values(array_unique(array_map(static fn (StatementRow $row): string => $row->amount, $rows))))
            ->where(static function (Builder $query) use ($fileKeys): void {
                $query->whereNull('external_hash')->orWhereNotIn('external_hash', $fileKeys);
            })
            ->get(['id', 'date', 'amount', 'created_at']);

        $matches = [];

        foreach ($rows as $row) {
            $best = null;
            $bestDistance = 0;

            foreach ($candidates as $candidate) {
                if ($candidate->amount !== $row->amount) {
                    continue;
                }

                $distance = (int) abs(Carbon::parse($row->date)->diffInDays($candidate->date, false));

                if ($distance > self::MATCH_WINDOW_DAYS) {
                    continue;
                }

                if ($best === null || $distance < $bestDistance || ($distance === $bestDistance && $candidate->created_at->lt($best->created_at))) {
                    $best = $candidate;
                    $bestDistance = $distance;
                }
            }

            if ($best !== null) {
                $matches[$row->reference] = $best->id;
            }
        }

        return $matches;
    }

    /**
     * Rows that look like injection are shown but never sent to the provider; a provider failure only drops the suggestions.
     *
     * @param  list<StatementRow>  $rows
     * @return array<int, ExpenseCategoryProposalData>
     */
    private function proposals(array $rows): array
    {
        $pending = [];

        foreach ($rows as $row) {
            $verdict = $this->promptGuard->scan(trim($row->counterparty."\n".$row->note));

            if ($verdict->flagged) {
                $this->promptGuard->record(null, 'bank_statement_row', current_tenant_id(), null, $verdict);

                continue;
            }

            $pending[] = new ExpenseRowData($row->row, $row->amount, $row->counterparty, $row->note);
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
}
