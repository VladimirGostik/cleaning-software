<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\CurrencyEnum;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseDuplicateActionEnum;
use Illuminate\Contracts\Validation\Validator;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class ExpenseBatchStoreData extends Data
{
    /** @param  DataCollection<int, ExpenseBatchLineData>  $lines */
    public function __construct(
        #[Required, DateFormat('Y-m-d')]
        public readonly string $date,
        public readonly ExpenseCategoryEnum $category,
        #[DataCollectionOf(ExpenseBatchLineData::class)]
        public readonly DataCollection $lines,
        #[Uuid]
        public readonly string|Optional $tenant_id = new Optional,
        public readonly CurrencyEnum $currency = CurrencyEnum::EUR,
        #[Nullable, Max(200)]
        public readonly ?string $supplier_name = null,
        #[Nullable, Max(5000)]
        public readonly ?string $description = null,
        #[Nullable, DateFormat('Y-m')]
        public readonly ?string $payroll_month = null,
        #[Nullable, Uuid]
        public readonly ?string $receipt_uuid = null,
        public readonly ?ExpenseProposalData $ai_proposal = null,
        public readonly ?ExpenseDuplicateActionEnum $duplicate_action = null,
    ) {}

    public function resolvedTenantId(): string
    {
        if ($this->tenant_id instanceof Optional) {
            return current_tenant_id();
        }

        return $this->tenant_id;
    }

    /** @return list<ExpenseBatchLineData> */
    public function lineItems(): array
    {
        return array_values(iterator_to_array($this->lines));
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'receipt_uuid' => ExpenseUpsertData::receiptRules(),
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(static function (Validator $validator): void {
            /** @var array<string, mixed> $data */
            $data = $validator instanceof \Illuminate\Validation\Validator ? $validator->getData() : [];
            $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
            $headerSupplier = filled($data['supplier_name'] ?? null);
            $anyEmployee = false;

            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                if (filled($line['tenant_membership_id'] ?? null)) {
                    $anyEmployee = true;
                } elseif (! filled($line['supplier_name'] ?? null) && ! $headerSupplier) {
                    $validator->errors()->add("lines.{$index}.supplier_name", __('app.expense_line_needs_recipient'));
                }
            }

            if ($anyEmployee && ! filled($data['payroll_month'] ?? null)) {
                $validator->errors()->add('payroll_month', __('app.expense_payroll_month_required'));
            }
        });
    }
}
