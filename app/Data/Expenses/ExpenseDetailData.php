<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Data\MediaFileData;
use App\Enums\CurrencyEnum;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use App\Models\Expense;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseDetailData extends Data
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
        public readonly ?string $description,
        public readonly ?string $bank_reference,
        public readonly ?MediaFileData $receipt,
        public readonly ?ExpenseProposalData $ai_proposal,
        public readonly ?string $confirmed_at,
        public readonly ExpenseCanData $can,
    ) {}

    /** Expects `tenant` and `media` eager loaded. */
    public static function fromModel(Expense $expense, ExpenseCanData $can, string $receiptUrl): self
    {
        $list = ExpenseListItemData::fromModel($expense);
        $media = $expense->getFirstMedia(Expense::RECEIPT_COLLECTION);

        return new self(
            id: $list->id,
            tenant_id: $list->tenant_id,
            tenant_name: $list->tenant_name,
            status: $list->status,
            date: $list->date,
            amount: $list->amount,
            currency: $list->currency,
            vat_amount: $list->vat_amount,
            supplier_name: $list->supplier_name,
            category: $list->category,
            source: $list->source,
            has_receipt: $list->has_receipt,
            proposed_category: $list->proposed_category,
            description: $expense->description,
            bank_reference: $expense->bank_reference,
            receipt: $media === null ? null : MediaFileData::fromMedia($media, $receiptUrl),
            ai_proposal: self::proposal($expense),
            confirmed_at: $expense->confirmed_at?->toIso8601String(),
            can: $can,
        );
    }

    private static function proposal(Expense $expense): ?ExpenseProposalData
    {
        $stored = $expense->ai_proposal;

        if ($stored === null) {
            return null;
        }

        $category = $stored['category'] ?? null;
        $tenantId = $stored['tenant_id'] ?? null;
        $confidence = $stored['confidence'] ?? 0;
        $reason = $stored['reason'] ?? null;

        return new ExpenseProposalData(
            category: is_string($category) ? ExpenseCategoryEnum::tryFrom($category) : null,
            tenant_id: is_string($tenantId) ? $tenantId : null,
            confidence: is_numeric($confidence) ? (float) $confidence : 0.0,
            reason: is_string($reason) ? $reason : null,
        );
    }
}
