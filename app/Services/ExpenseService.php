<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Ai\AnalyzesExpenses;
use App\Data\Auth\MobileCompanyData;
use App\Data\Expenses\ExpenseAnalysisContextData;
use App\Data\Expenses\ExpenseAnalyzeData;
use App\Data\Expenses\ExpenseCanData;
use App\Data\Expenses\ExpenseCompanyContextData;
use App\Data\Expenses\ExpenseConfirmData;
use App\Data\Expenses\ExpenseDetailData;
use App\Data\Expenses\ExpenseDraftData;
use App\Data\Expenses\ExpenseFormContextData;
use App\Data\Expenses\ExpenseListItemData;
use App\Data\Expenses\ExpenseProposalData;
use App\Data\Expenses\ExpenseUpsertData;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use App\Exceptions\AiUnavailableException;
use App\Models\Expense;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Expenses\BankStatementParser;
use App\Services\Tenancy\TenantPermissionSnapshot;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use App\Utils\PerPage;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ExpenseService
{
    public function __construct(
        private DatabaseManager $db,
        private TenantPermissionSnapshot $snapshot,
        private TemporaryUploadService $uploads,
        private AnalyzesExpenses $analyzer,
        private BankStatementParser $parser,
    ) {}

    /** @return LengthAwarePaginator<int, ExpenseListItemData> */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return $this->page(Expense::query(), $request);
    }

    /**
     * The tenant scope only knows the bound tenant, so the firm set comes from the permission snapshot.
     *
     * @return LengthAwarePaginator<int, ExpenseListItemData>
     */
    public function listAcrossCompanies(Request $request, User $actor): LengthAwarePaginator
    {
        $tenantIds = TenantVisibility::idsWhere(
            $this->snapshot->forRequest($actor),
            static fn (TenantVisibility $visibility): bool => $visibility->expenses,
        );

        return $this->page(
            Expense::query()->withoutGlobalScope(TenantScope::class)->whereIn('expenses.tenant_id', $tenantIds),
            $request,
        );
    }

    public function formContext(User $actor): ExpenseFormContextData
    {
        return new ExpenseFormContextData(
            companies: array_map(MobileCompanyData::fromVisibility(...), $this->manageableVisibilities($actor)),
            categories: ExpenseCategoryEnum::cases(),
        );
    }

    public function detail(Expense $expense, User $actor, string $receiptRoute = 'expenses.receipt'): ExpenseDetailData
    {
        $expense->loadMissing(['tenant:id,name', 'media']);

        return ExpenseDetailData::fromModel(
            $expense,
            new ExpenseCanData(
                update: $actor->can('update', $expense),
                delete: $actor->can('delete', $expense),
                confirm: $expense->isDraft() && $actor->can('confirm', $expense),
            ),
            route($receiptRoute, $expense),
        );
    }

    public function assertMayCreate(ExpenseUpsertData $data, User $actor): void
    {
        Gate::forUser($actor)->authorize('create', [Expense::class, $data->resolvedTenantId()]);
    }

    public function create(ExpenseUpsertData $data, User $actor, string $sessionId): Expense
    {
        $tenantId = $data->resolvedTenantId();
        $status = $this->statusFor($data, null);

        if ($data->receipt_uuid !== null && $tenantId !== current_tenant_id()) {
            throw ValidationException::withMessages(['receipt_uuid' => [__('app.expense_receipt_tenant_mismatch')]]);
        }

        return $this->db->transaction(function () use ($data, $actor, $sessionId, $tenantId, $status): Expense {
            $expense = Expense::create([
                ...$this->attributes($data),
                'tenant_id' => $tenantId,
                'created_by_user_id' => $actor->id,
                'status' => $status,
                'source' => $data->receipt_uuid !== null ? ExpenseSourceEnum::Receipt : $data->source,
                'ai_proposal' => $this->storedProposal($data->ai_proposal),
                'confirmed_at' => $status === ExpenseStatusEnum::Confirmed ? now() : null,
            ]);

            if ($data->receipt_uuid !== null) {
                $this->uploads->moveToModel($expense, Expense::RECEIPT_COLLECTION, $data->receipt_uuid, $actor, $sessionId);
            }

            return $this->hydrate($expense);
        });
    }

    /** The tenant never changes on update. */
    public function update(Expense $expense, ExpenseUpsertData $data, User $actor, string $sessionId): Expense
    {
        $status = $this->statusFor($data, $expense);

        return $this->db->transaction(function () use ($expense, $data, $actor, $sessionId, $status): Expense {
            $expense->update([
                ...$this->attributes($data),
                'status' => $status,
                'confirmed_at' => $status === ExpenseStatusEnum::Confirmed ? ($expense->confirmed_at ?? now()) : null,
            ]);

            if ($data->receipt_uuid !== null) {
                $this->uploads->moveToModel($expense, Expense::RECEIPT_COLLECTION, $data->receipt_uuid, $actor, $sessionId);
            }

            return $this->hydrate($expense);
        });
    }

    public function confirm(Expense $expense, ExpenseConfirmData $data): Expense
    {
        if (! $expense->isDraft()) {
            throw ValidationException::withMessages(['status' => [__('app.expense_already_confirmed')]]);
        }

        return $this->db->transaction(function () use ($expense, $data): Expense {
            $expense->update([
                'status' => ExpenseStatusEnum::Confirmed,
                'category' => $data->category,
                'confirmed_at' => now(),
            ]);

            return $this->hydrate($expense);
        });
    }

    public function delete(Expense $expense): void
    {
        $this->db->transaction(fn () => $expense->delete());
    }

    /** Direct attach for token clients that have no staged-upload session. */
    public function attachReceipt(Expense $expense, UploadedFile $file): Expense
    {
        return $this->db->transaction(function () use ($expense, $file): Expense {
            $expense->addMedia($file)->toMediaCollection(Expense::RECEIPT_COLLECTION);

            return $this->hydrate($expense->unsetRelation('media'));
        });
    }

    public function analyze(ExpenseAnalyzeData $data, User $actor): ExpenseDraftData
    {
        $context = $this->analysisContext($actor);

        try {
            $draft = $data->receipt !== null
                ? $this->analyzer->analyzeReceipt($data->receipt->getContent(), (string) $data->receipt->getMimeType(), $context)
                : $this->analyzer->analyzeText((string) $data->text, $context);
        } catch (AiUnavailableException) {
            throw ValidationException::withMessages(['ai' => [__('app.ai_unavailable')]]);
        }

        return $this->guardDraft($draft, $context);
    }

    public function analysisContext(User $actor): ExpenseAnalysisContextData
    {
        return new ExpenseAnalysisContextData(
            companies: array_map(
                static fn (TenantVisibility $visibility): ExpenseCompanyContextData => new ExpenseCompanyContextData(
                    id: $visibility->tenant->id,
                    name: $visibility->tenant->name,
                    ico: $visibility->tenant->ico,
                    iban: $visibility->tenant->iban,
                ),
                $this->manageableVisibilities($actor),
            ),
            today: now()->toDateString(),
        );
    }

    /**
     * @param  Builder<Expense>  $base
     * @return LengthAwarePaginator<int, ExpenseListItemData>
     */
    private function page(Builder $base, Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callbackClean('search', function (Builder $query, mixed $value): void {
                    if (blank($value) || ! is_scalar($value)) {
                        return;
                    }

                    $like = '%'.Filters::escapeLikeValue((string) $value).'%';
                    $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

                    $query->where(function (Builder $q) use ($like, $operator): void {
                        $q->where('expenses.supplier_name', $operator, $like)->orWhere('expenses.description', $operator, $like);
                    });
                }),
                AllowedFilter::dynamic('status'),
                AllowedFilter::dynamic('category'),
                AllowedFilter::dynamic('source'),
                AllowedFilter::dynamic('tenant_id')->uuid(),
                AllowedFilter::dynamic('date')->date(),
            )
            ->allowedSorts('date', 'amount', 'created_at', 'status', 'supplier_name')
            ->defaultSort('-date', '-created_at')
            ->with(['tenant:id,name', 'media'])
            ->paginate(PerPage::clamp($request)->integer('per_page'))
            ->withQueryString()
            ->through(fn (Expense $expense) => ExpenseListItemData::fromModel($expense));
    }

    /** @return list<TenantVisibility> */
    private function manageableVisibilities(User $actor): array
    {
        return array_values(array_filter(
            $this->snapshot->forRequest($actor),
            static fn (TenantVisibility $visibility): bool => $visibility->manageExpenses,
        ));
    }

    /** @return array<string, mixed> */
    private function attributes(ExpenseUpsertData $data): array
    {
        return [
            'date' => $data->date,
            'amount' => $data->amount,
            'currency' => $data->currency,
            'vat_amount' => $data->vat_amount,
            'supplier_name' => $this->parser->cleanText($data->supplier_name, 200, false),
            'description' => $data->description,
            'category' => $data->category,
        ];
    }

    private function statusFor(ExpenseUpsertData $data, ?Expense $existing): ExpenseStatusEnum
    {
        $status = $data->status ?? ($data->category !== null ? ExpenseStatusEnum::Confirmed : ($existing->status ?? ExpenseStatusEnum::Draft));

        if ($status === ExpenseStatusEnum::Confirmed && $data->category === null) {
            throw ValidationException::withMessages(['category' => [__('app.expense_confirm_requires_category')]]);
        }

        return $status;
    }

    /** @return array<string, mixed>|null */
    private function storedProposal(?ExpenseProposalData $proposal): ?array
    {
        if ($proposal === null) {
            return null;
        }

        return [
            'category' => $proposal->category?->value,
            'tenant_id' => $proposal->tenant_id,
            'confidence' => $proposal->confidence,
            'reason' => $proposal->reason,
            'model' => config('ai.model'),
        ];
    }

    private function hydrate(Expense $expense): Expense
    {
        return $expense->load(['tenant:id,name', 'media']);
    }

    /** AI output is untrusted: amounts, dates, text and the proposed firm are re-validated here. */
    private function guardDraft(ExpenseDraftData $draft, ExpenseAnalysisContextData $context): ExpenseDraftData
    {
        $eligible = array_map(static fn (ExpenseCompanyContextData $company): string => $company->id, $context->companies);
        $proposal = $draft->proposal;
        $amount = $this->decimal($draft->amount);

        return new ExpenseDraftData(
            date: $this->validDate($draft->date),
            amount: $amount !== null && (float) $amount > 0 ? $amount : null,
            currency: $draft->currency,
            vat_amount: $this->decimal($draft->vat_amount),
            supplier_name: $this->parser->cleanText($draft->supplier_name, 200),
            description: $this->parser->cleanText($draft->description, 500),
            proposal: new ExpenseProposalData(
                category: $proposal->category,
                tenant_id: in_array($proposal->tenant_id, $eligible, true) ? $proposal->tenant_id : null,
                confidence: max(0.0, min(1.0, $proposal->confidence)),
                reason: $this->parser->cleanText($proposal->reason, 500),
            ),
        );
    }

    private function decimal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalised = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $value);

        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $normalised) !== 1) {
            return null;
        }

        return number_format((float) $normalised, 2, '.', '');
    }

    private function validDate(?string $value): ?string
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }
}
