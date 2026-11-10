<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\CurrencyEnum;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use App\Models\Expense;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenant_id,
        public readonly ?string $tenant_name,
        public readonly ExpenseStatusEnum $status,
        public readonly string $date,
        public readonly string $amount,
        public readonly CurrencyEnum $currency,
        public readonly ?string $vat_amount,
        public readonly ?string $supplier_name,
        public readonly ?ExpenseCategoryEnum $category,
        public readonly ExpenseSourceEnum $source,
        public readonly bool $has_receipt,
        public readonly ?ExpenseCategoryEnum $proposed_category,
        public readonly ?string $tenant_membership_id = null,
        public readonly ?string $employee_name = null,
        public readonly ?string $payroll_month = null,
        public readonly bool $is_undocumented = false,
        public readonly ?string $description = null,
    ) {}

    /** Expects `tenant`, `media` and `employee.user` eager loaded. */
    public static function fromModel(Expense $expense): self
    {
        return new self(
            id: $expense->id,
            tenant_id: $expense->tenant_id,
            tenant_name: $expense->tenant->name,
            status: $expense->status,
            date: $expense->date->toDateString(),
            amount: $expense->amount,
            currency: $expense->currency,
            vat_amount: $expense->vat_amount,
            supplier_name: $expense->supplier_name,
            category: $expense->category,
            source: $expense->source,
            has_receipt: $expense->getFirstMedia(Expense::RECEIPT_COLLECTION) !== null,
            proposed_category: self::proposedCategory($expense),
            tenant_membership_id: $expense->tenant_membership_id,
            employee_name: $expense->employee?->display_name,
            payroll_month: $expense->payroll_month?->format('Y-m'),
            is_undocumented: $expense->is_undocumented,
            description: $expense->description,
        );
    }

    private static function proposedCategory(Expense $expense): ?ExpenseCategoryEnum
    {
        $value = $expense->ai_proposal['category'] ?? null;

        return is_string($value) ? ExpenseCategoryEnum::tryFrom($value) : null;
    }
}
