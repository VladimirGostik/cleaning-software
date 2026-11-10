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
use App\Data\Expenses\ExpenseDraftLineData;
use App\Data\Expenses\ExpenseEmployeeOptionData;
use App\Data\Expenses\ExpenseFormContextData;
use App\Data\Expenses\ExpenseListItemData;
use App\Data\Expenses\ExpenseProposalData;
use App\Data\Expenses\ExpenseTotalData;
use App\Data\Expenses\ExpenseUpsertData;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use App\Exceptions\AiUnavailableException;
use App\Models\Expense;
use App\Models\TenantMembership;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Services\Ai\Guard\PromptGuard;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Expenses\EmployeeExpenseService;
use App\Services\Expenses\ExpenseListQuery;
use App\Services\Tenancy\TenantPermissionSnapshot;
use App\Support\SafeText;
use App\Utils\PerPage;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class ExpenseService
{
    private const int MAX_DRAFT_LINES = 50;

    public function __construct(
        private DatabaseManager $db,
        private TenantPermissionSnapshot $snapshot,
        private TemporaryUploadService $uploads,
        private AnalyzesExpenses $analyzer,
        private PromptGuard $promptGuard,
        private EmployeeExpenseService $employeeExpenses,
        private ExpenseListQuery $list,
    ) {}

    /** @return LengthAwarePaginator<int, ExpenseListItemData> */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return $this->page(Expense::query(), $request);
    }

    /** @return list<ExpenseTotalData> */
    public function totals(Request $request): array
    {
        return $this->list->totals(Expense::query(), $request);
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
            employees: $this->employeeOptions($actor),
        );
    }

    /** @return list<ExpenseEmployeeOptionData> */
    private function employeeOptions(User $actor): array
    {
        $tenantIds = TenantVisibility::idsWhere(
            $this->snapshot->forRequest($actor),
            static fn (TenantVisibility $visibility): bool => $visibility->expenses && $visibility->readsEmployeePersonalData(),
        );

        if ($tenantIds === []) {
            return [];
        }

        return array_values(TenantMembership::query()
            ->with('user:id,name,email')
            ->whereIn('tenant_id', $tenantIds)
            ->orderByDesc('is_active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (TenantMembership $membership): ExpenseEmployeeOptionData => ExpenseEmployeeOptionData::fromModel($membership))
            ->all());
    }

    public function detail(Expense $expense, User $actor, string $receiptRoute = 'expenses.receipt'): ExpenseDetailData
    {
        $expense->loadMissing(['tenant:id,name', 'media', 'employee.user']);

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
        Gate::forUser($actor)->authorize(
            $data->employeeId() === null ? 'create' : 'createForEmployee',
            [Expense::class, $data->resolvedTenantId()],
        );
    }

    public function create(ExpenseUpsertData $data, User $actor, string $sessionId): Expense
    {
        $tenantId = $data->resolvedTenantId();
        $status = $this->statusFor($data, null);

        if ($data->receipt_uuid !== null && $tenantId !== current_tenant_id()) {
            throw ValidationException::withMessages(['receipt_uuid' => [__('app.expense_receipt_tenant_mismatch')]]);
        }

        return $this->db->transaction(function () use ($data, $actor, $sessionId, $tenantId, $status): Expense {
            $this->employeeExpenses->guardSingle($data, $tenantId, null);

            $expense = Expense::create([
                ...$this->attributes($data),
                'tenant_id' => $tenantId,
                'created_by_user_id' => $actor->id,
                'status' => $status,
                'source' => $data->receipt_uuid !== null ? ExpenseSourceEnum::Receipt : $data->source,
                'ai_proposal' => $data->ai_proposal?->stored(),
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
        if ($data->omitsEmployeeFields()) {
            $data = $data->keepingEmployeeOf($expense);
        }

        $status = $this->statusFor($data, $expense);

        if ($data->employeeId() !== null) {
            Gate::forUser($actor)->authorize('createForEmployee', [Expense::class, $expense->tenant_id]);
        }

        return $this->db->transaction(function () use ($expense, $data, $actor, $sessionId, $status): Expense {
            $this->employeeExpenses->guardSingle($data, $expense->tenant_id, $expense);

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

    /**
     * @param  list<Expense>  $expenses
     * @return list<Expense>
     */
    public function attachReceiptToMany(array $expenses, UploadedFile $file): array
    {
        return $this->db->transaction(function () use ($expenses, $file): array {
            $ids = array_map(static fn (Expense $expense): string => $expense->id, $expenses);
            $locked = Expense::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($expenses as $index => $expense) {
                $current = $locked->get($expense->id) ?? $expense;

                if ($current->is_undocumented) {
                    throw ValidationException::withMessages(['expense_ids.'.$index => [__('app.expense_receipt_undocumented')]]);
                }

                if ($current->media()->where('collection_name', Expense::RECEIPT_COLLECTION)->exists()) {
                    throw ValidationException::withMessages(['expense_ids.'.$index => [__('app.expense_receipt_exists')]]);
                }
            }

            $stored = [];

            try {
                foreach ($expenses as $expense) {
                    $stored[] = $stored === []
                        ? $expense->addMedia($file)->toMediaCollection(Expense::RECEIPT_COLLECTION)
                        : $stored[0]->copy($expense, Expense::RECEIPT_COLLECTION);
                }
            } catch (Throwable $exception) {
                foreach ($stored as $media) {
                    $media->delete();
                }

                throw $exception;
            }

            return array_map(fn (Expense $expense): Expense => $this->hydrate($expense->unsetRelation('media')), $expenses);
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

        $draft = $this->guardDraft($draft, $context);
        $draft = $this->neutraliseInjection($draft, (string) $data->text, $actor);

        return $this->withMatchedEmployees($draft, $actor);
    }

    /** Text planted in a receipt or an email must not steer the proposal the user is asked to accept. */
    private function neutraliseInjection(ExpenseDraftData $draft, string $source, User $actor): ExpenseDraftData
    {
        $lineNames = array_map(static fn (ExpenseDraftLineData $line): ?string => $line->name, $draft->lines);
        $output = implode("\n", array_filter([$draft->supplier_name, $draft->description, $draft->proposal->reason, ...$lineNames]));
        $text = trim($source."\n".$output);
        $verdict = $this->promptGuard->scan($text);

        if (! $verdict->flagged) {
            return $draft;
        }

        $this->promptGuard->record($actor, 'expense_analysis', null, null, $verdict);

        return new ExpenseDraftData(
            date: $draft->date,
            amount: $draft->amount,
            currency: $draft->currency,
            vat_amount: $draft->vat_amount,
            supplier_name: $this->promptGuard->scan((string) $draft->supplier_name)->flagged ? null : $draft->supplier_name,
            description: null,
            proposal: new ExpenseProposalData(
                category: null,
                tenant_id: null,
                confidence: 0.0,
                reason: __('app.expense_guard_notice'),
            ),
        );
    }

    private function withMatchedEmployees(ExpenseDraftData $draft, User $actor): ExpenseDraftData
    {
        $tenantId = $draft->proposal->tenant_id ?? current_tenant_id();

        if ($draft->lines === [] || ! Gate::forUser($actor)->allows('createForEmployee', [Expense::class, $tenantId])) {
            return $draft;
        }

        return new ExpenseDraftData(
            date: $draft->date,
            amount: $draft->amount,
            currency: $draft->currency,
            vat_amount: $draft->vat_amount,
            supplier_name: $draft->supplier_name,
            description: $draft->description,
            proposal: $draft->proposal,
            payroll_month: $draft->payroll_month,
            lines: $this->employeeExpenses->matchDraftLines($draft->lines, $tenantId),
        );
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
        return $this->list->filtered($base, $request)
            ->allowedSorts('date', 'amount', 'created_at', 'status', 'supplier_name')
            ->defaultSort('-date', '-created_at')
            ->with(['tenant:id,name', 'media', 'employee.user'])
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
            'supplier_name' => SafeText::clean($data->supplier_name, 200, false),
            'description' => $data->description,
            'category' => $data->category,
            'tenant_membership_id' => $data->employeeId(),
            'payroll_month' => $data->month() === null ? null : $data->month().'-01',
            'is_undocumented' => $data->undocumented(),
        ];
    }

    private function statusFor(ExpenseUpsertData $data, ?Expense $existing): ExpenseStatusEnum
    {
        if ($data->employeeId() !== null) {
            if ($data->category === null) {
                throw ValidationException::withMessages(['category' => [__('app.expense_confirm_requires_category')]]);
            }

            return ExpenseStatusEnum::Confirmed;
        }

        $status = $data->status ?? ($data->category !== null ? ExpenseStatusEnum::Confirmed : ($existing->status ?? ExpenseStatusEnum::Draft));

        if ($status === ExpenseStatusEnum::Confirmed && $data->category === null) {
            throw ValidationException::withMessages(['category' => [__('app.expense_confirm_requires_category')]]);
        }

        return $status;
    }

    private function hydrate(Expense $expense): Expense
    {
        return $expense->load(['tenant:id,name', 'media', 'employee.user']);
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
            supplier_name: SafeText::clean($draft->supplier_name, 200),
            description: SafeText::clean($draft->description, 500),
            proposal: new ExpenseProposalData(
                category: $proposal->category,
                tenant_id: in_array($proposal->tenant_id, $eligible, true) ? $proposal->tenant_id : null,
                confidence: max(0.0, min(1.0, $proposal->confidence)),
                reason: SafeText::clean($proposal->reason, 500),
            ),
            payroll_month: $this->validMonth($draft->payroll_month),
            lines: $this->guardLines($draft->lines),
        );
    }

    /**
     * @param  list<ExpenseDraftLineData>  $lines
     * @return list<ExpenseDraftLineData>
     */
    private function guardLines(array $lines): array
    {
        return array_map(
            fn (ExpenseDraftLineData $line): ExpenseDraftLineData => new ExpenseDraftLineData(
                name: SafeText::clean($line->name, 200),
                amount: $this->decimal($line->amount),
                payroll_month: $this->validMonth($line->payroll_month),
            ),
            array_slice($lines, 0, self::MAX_DRAFT_LINES),
        );
    }

    private function validMonth(?string $value): ?string
    {
        return $value !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1 ? $value : null;
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
