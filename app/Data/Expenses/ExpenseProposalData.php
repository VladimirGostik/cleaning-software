<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\ExpenseCategoryEnum;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseProposalData extends Data
{
    public function __construct(
        public readonly ?ExpenseCategoryEnum $category,
        #[Nullable, Uuid]
        public readonly ?string $tenant_id,
        #[Between(0, 1)]
        public readonly float $confidence,
        #[Nullable, Max(500)]
        public readonly ?string $reason = null,
    ) {}
}
