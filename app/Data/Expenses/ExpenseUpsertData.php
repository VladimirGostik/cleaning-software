<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\CurrencyEnum;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseDuplicateActionEnum;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use App\Models\Expense;
use App\Rules\OwnedTemporaryMedia;
use App\Rules\TemporaryMediaConstraints;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class ExpenseUpsertData extends Data
{
    public function __construct(
        #[Required, DateFormat('Y-m-d')]
        public readonly string $date,
        #[Required]
        public readonly string $amount,
        #[Uuid]
        public readonly string|Optional $tenant_id = new Optional,
        public readonly CurrencyEnum $currency = CurrencyEnum::EUR,
        public readonly ?string $vat_amount = null,
        #[Nullable, Max(200)]
        public readonly ?string $supplier_name = null,
        #[Nullable, Max(5000)]
        public readonly ?string $description = null,
        public readonly ?ExpenseCategoryEnum $category = null,
        public readonly ?ExpenseStatusEnum $status = null,
        #[Nullable, Uuid]
        public readonly ?string $receipt_uuid = null,
        public readonly ExpenseSourceEnum $source = ExpenseSourceEnum::Manual,
        public readonly ?ExpenseProposalData $ai_proposal = null,
        #[Nullable, Uuid]
        public readonly string|null|Optional $tenant_membership_id = new Optional,
        #[Nullable, DateFormat('Y-m')]
        public readonly string|null|Optional $payroll_month = new Optional,
        public readonly bool|Optional $is_undocumented = new Optional,
        public readonly ?ExpenseDuplicateActionEnum $duplicate_action = null,
    ) {}

    /** Omitted `tenant_id` means the bound (active) tenant; an explicit null never validates. */
    public function resolvedTenantId(): string
    {
        if ($this->tenant_id instanceof Optional) {
            return current_tenant_id();
        }

        return $this->tenant_id;
    }

    public function employeeId(): ?string
    {
        return $this->tenant_membership_id instanceof Optional ? null : $this->tenant_membership_id;
    }

    public function month(): ?string
    {
        return $this->payroll_month instanceof Optional ? null : $this->payroll_month;
    }

    public function undocumented(): bool
    {
        return $this->is_undocumented instanceof Optional ? false : $this->is_undocumented;
    }

    /** Clients that predate the employee fields send none of them, and must leave the link as it is. */
    public function omitsEmployeeFields(): bool
    {
        return $this->tenant_membership_id instanceof Optional
            && $this->payroll_month instanceof Optional
            && $this->is_undocumented instanceof Optional;
    }

    public function keepingEmployeeOf(Expense $expense): self
    {
        return self::from([
            ...$this->toArray(),
            'tenant_membership_id' => $expense->tenant_membership_id,
            'payroll_month' => $expense->payroll_month?->format('Y-m'),
            'is_undocumented' => $expense->is_undocumented,
        ]);
    }

    public function resolvedStatus(): ExpenseStatusEnum
    {
        return $this->status ?? ($this->category === null ? ExpenseStatusEnum::Draft : ExpenseStatusEnum::Confirmed);
    }

    /** @return array<string, mixed> */
    public static function rules(ValidationContext $context): array
    {
        $payload = is_array($context->payload) ? $context->payload : [];
        $undocumented = filter_var($payload['is_undocumented'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hasEmployee = filled($payload['tenant_membership_id'] ?? null);

        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'vat_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'lte:amount'],
            'source' => [Rule::in([ExpenseSourceEnum::Manual->value, ExpenseSourceEnum::Receipt->value])],
            'category' => $hasEmployee ? ['required'] : ['required_if:status,'.ExpenseStatusEnum::Confirmed->value],
            'is_undocumented' => ['boolean'],
            'tenant_membership_id' => $undocumented ? ['required', 'uuid'] : ['nullable', 'uuid'],
            'payroll_month' => $hasEmployee && ! $undocumented ? ['required', 'date_format:Y-m'] : ['nullable', 'date_format:Y-m'],
            'receipt_uuid' => $undocumented ? ['prohibited'] : self::receiptRules(),
        ];
    }

    /** @return list<mixed> */
    public static function receiptRules(): array
    {
        /** @var list<string> $mimes */
        $mimes = config('expenses.receipt.allowed_mimes', []);
        $maxKb = config('expenses.receipt.max_size_kb', 10240);

        return request()->hasSession()
            ? [
                'nullable', 'uuid', new OwnedTemporaryMedia,
                new TemporaryMediaConstraints(
                    $mimes,
                    is_numeric($maxKb) ? (int) $maxKb : 10240,
                    'app.expense_receipt_invalid_type',
                    'app.expense_receipt_too_large',
                ),
            ]
            : ['prohibited'];
    }
}
