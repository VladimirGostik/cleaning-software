<?php

declare(strict_types=1);

namespace App\Services\Expenses;

use App\Data\Expenses\ExpenseBatchStoreData;
use App\Data\Expenses\ExpenseDraftLineData;
use App\Data\Expenses\ExpenseListItemData;
use App\Data\Expenses\ExpenseUpsertData;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseDuplicateActionEnum;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use App\Models\Expense;
use App\Models\TenantMembership;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Services\TemporaryUploadService;
use App\Support\SafeText;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class EmployeeExpenseService
{
    public function __construct(
        private DatabaseManager $db,
        private TemporaryUploadService $uploads,
    ) {}

    /** @return list<Expense> */
    public function createBatch(ExpenseBatchStoreData $data, User $actor, string $sessionId): array
    {
        $tenantId = $data->resolvedTenantId();
        $lines = $data->lineItems();
        $hasEmployee = array_any($lines, static fn ($line): bool => $line->tenant_membership_id !== null);

        Gate::forUser($actor)->authorize($hasEmployee ? 'createForEmployee' : 'create', [Expense::class, $tenantId]);

        if ($data->receipt_uuid !== null && $tenantId !== current_tenant_id()) {
            throw ValidationException::withMessages(['receipt_uuid' => [__('app.expense_receipt_tenant_mismatch')]]);
        }

        return $this->db->transaction(function () use ($data, $actor, $sessionId, $tenantId, $lines): array {
            $employees = [];
            $pairs = [];
            $seen = [];

            foreach ($lines as $index => $line) {
                if ($line->tenant_membership_id === null) {
                    continue;
                }

                $employees[$line->tenant_membership_id] ??= $this->assertMembershipInTenant(
                    $line->tenant_membership_id,
                    $tenantId,
                    "lines.{$index}.tenant_membership_id",
                );

                if ($data->category !== ExpenseCategoryEnum::Wages || $data->payroll_month === null) {
                    continue;
                }

                $key = $line->tenant_membership_id.'|'.$data->payroll_month;

                if (isset($seen[$key])) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.tenant_membership_id" => [__('app.expense_duplicate_in_batch', [
                            'employee' => $employees[$line->tenant_membership_id]->display_name,
                            'month' => $this->monthLabel($data->payroll_month),
                        ])],
                    ]);
                }

                $seen[$key] = true;
                $pairs[] = ['index' => $index, 'membership_id' => $line->tenant_membership_id, 'month' => $data->payroll_month];
            }

            $this->resolveDuplicates($tenantId, $pairs, $data->duplicate_action, null);

            $created = [];
            $receipt = null;

            foreach ($lines as $line) {
                $employee = $line->tenant_membership_id === null ? null : $employees[$line->tenant_membership_id];

                $expense = Expense::create([
                    'tenant_id' => $tenantId,
                    'created_by_user_id' => $actor->id,
                    'status' => ExpenseStatusEnum::Confirmed,
                    'date' => $data->date,
                    'amount' => $line->amount,
                    'currency' => $data->currency,
                    'supplier_name' => SafeText::clean($line->supplier_name ?? ($employee instanceof TenantMembership ? $employee->display_name : $data->supplier_name), 200, false),
                    'description' => $line->description ?? $data->description,
                    'category' => $data->category,
                    'source' => $data->receipt_uuid !== null ? ExpenseSourceEnum::Receipt : ExpenseSourceEnum::Manual,
                    'ai_proposal' => $data->ai_proposal?->stored(),
                    'confirmed_at' => now(),
                    'tenant_membership_id' => $employee?->id,
                    'payroll_month' => $data->payroll_month === null ? null : $data->payroll_month.'-01',
                    'is_undocumented' => false,
                ]);

                if ($data->receipt_uuid !== null) {
                    $receipt = $receipt === null
                        ? $this->uploads->moveToModel($expense, Expense::RECEIPT_COLLECTION, $data->receipt_uuid, $actor, $sessionId)
                        : $receipt->copy($expense, Expense::RECEIPT_COLLECTION);
                }

                $created[] = $expense->load(['tenant:id,name', 'media', 'employee.user']);
            }

            return $created;
        });
    }

    public function guardSingle(ExpenseUpsertData $data, string $tenantId, ?Expense $existing): void
    {
        $employeeId = $data->employeeId();
        $month = $data->month();

        if ($employeeId === null) {
            return;
        }

        $this->assertMembershipInTenant($employeeId, $tenantId);

        if ($data->undocumented() || $data->category !== ExpenseCategoryEnum::Wages || $month === null) {
            return;
        }

        $unchanged = $existing instanceof Expense
            && $existing->tenant_membership_id === $employeeId
            && $existing->payroll_month?->format('Y-m') === $month
            && $existing->category === ExpenseCategoryEnum::Wages
            && ! $existing->is_undocumented;

        if ($unchanged) {
            return;
        }

        $this->resolveDuplicates(
            $tenantId,
            [['index' => null, 'membership_id' => $employeeId, 'month' => $month]],
            $data->duplicate_action,
            $existing?->id,
        );
    }

    /** @param  list<array{index: int|null, membership_id: string, month: string}>  $pairs */
    public function resolveDuplicates(string $tenantId, array $pairs, ?ExpenseDuplicateActionEnum $action, ?string $ignoreExpenseId): void
    {
        if ($pairs === []) {
            return;
        }

        $existing = Expense::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('category', ExpenseCategoryEnum::Wages->value)
            ->where('is_undocumented', false)
            ->when($ignoreExpenseId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreExpenseId))
            ->where(function (Builder $query) use ($pairs): void {
                foreach ($pairs as $pair) {
                    $query->orWhere(fn (Builder $q) => $q
                        ->where('tenant_membership_id', $pair['membership_id'])
                        ->whereDate('payroll_month', $pair['month'].'-01'));
                }
            })
            ->with('employee.user')
            ->lockForUpdate()
            ->get();

        if ($existing->isEmpty()) {
            return;
        }

        if ($action === ExpenseDuplicateActionEnum::KeepBoth) {
            return;
        }

        if ($action === ExpenseDuplicateActionEnum::Replace) {
            $existing->each(fn (Expense $expense): ?bool => $expense->delete());

            return;
        }

        $errors = ['duplicate' => [__('app.expense_duplicate')]];

        foreach ($pairs as $pair) {
            $match = $existing->first(fn (Expense $expense): bool => $expense->tenant_membership_id === $pair['membership_id']
                && $expense->payroll_month?->format('Y-m') === $pair['month']);

            if ($match === null) {
                continue;
            }

            $key = $pair['index'] === null ? 'tenant_membership_id' : "lines.{$pair['index']}.tenant_membership_id";
            $errors[$key] = [__('app.expense_duplicate_line', [
                'employee' => $match->employee->display_name ?? '',
                'month' => $this->monthLabel($pair['month']),
                'amount' => $match->currency->format((float) $match->amount),
            ])];
        }

        throw ValidationException::withMessages($errors);
    }

    public function assertMembershipInTenant(string $membershipId, string $tenantId, string $errorKey = 'tenant_membership_id'): TenantMembership
    {
        $membership = TenantMembership::query()
            ->with('user:id,name,email')
            ->where('tenant_id', $tenantId)
            ->find($membershipId);

        if (! $membership instanceof TenantMembership) {
            throw ValidationException::withMessages([$errorKey => [__('app.expense_employee_invalid')]]);
        }

        return $membership;
    }

    /** Folded, order-free name match; one hit among active people, else one among inactive, else null. */
    public function matchEmployee(string $name, string $tenantId): ?TenantMembership
    {
        $matches = $this->nameMatches($name, $tenantId);
        $active = $matches->where('is_active', true);

        if ($active->isNotEmpty()) {
            return $active->count() === 1 ? $active->first() : null;
        }

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** For callers that must tell the person why there is no match. */
    public function resolveByName(string $name, string $tenantId): TenantMembership
    {
        $employee = $this->matchEmployee($name, $tenantId);

        if ($employee instanceof TenantMembership) {
            return $employee;
        }

        $message = $this->nameMatches($name, $tenantId)->count() > 1
            ? __('app.expense_employee_ambiguous')
            : __('app.expense_employee_not_found');

        throw ValidationException::withMessages(['employee_name' => [$message]]);
    }

    /** @return Collection<int, TenantMembership> */
    private function nameMatches(string $name, string $tenantId): Collection
    {
        $wanted = $this->tokens($name);

        if ($wanted === []) {
            return new Collection;
        }

        return TenantMembership::query()
            ->with('user:id,name,email')
            ->where('tenant_id', $tenantId)
            ->get()
            ->filter(fn (TenantMembership $membership): bool => $this->tokens($this->nameOf($membership)) === $wanted)
            ->values();
    }

    /**
     * @param  list<ExpenseDraftLineData>  $lines
     * @return list<ExpenseDraftLineData>
     */
    public function matchDraftLines(array $lines, string $tenantId): array
    {
        return array_map(function (ExpenseDraftLineData $line) use ($tenantId): ExpenseDraftLineData {
            $match = $line->name === null ? null : $this->matchEmployee($line->name, $tenantId);

            return new ExpenseDraftLineData(
                $line->name,
                $line->amount,
                $line->payroll_month,
                $match?->id,
                $match?->display_name,
            );
        }, $lines);
    }

    /** @return list<ExpenseListItemData> */
    public function forEmployee(TenantMembership $employee, int $limit = 50): array
    {
        return array_values(Expense::query()
            ->where('tenant_membership_id', $employee->id)
            ->with(['tenant:id,name', 'media', 'employee.user'])
            ->orderByDesc('payroll_month')
            ->orderByDesc('date')
            ->limit($limit)
            ->get()
            ->map(fn (Expense $expense): ExpenseListItemData => ExpenseListItemData::fromModel($expense))
            ->all());
    }

    private function nameOf(TenantMembership $membership): string
    {
        $name = trim(($membership->first_name ?? '').' '.($membership->last_name ?? ''));

        return $name !== '' ? $name : $membership->display_name;
    }

    /** @return list<string> */
    private function tokens(string $name): array
    {
        $tokens = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($tokens);

        return $tokens;
    }

    private function monthLabel(string $month): string
    {
        [$year, $number] = explode('-', $month);

        return $number.'/'.$year;
    }
}
