<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\ExpenseCategoryEnum;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseConfirmData extends Data
{
    public function __construct(
        #[Required]
        public readonly ExpenseCategoryEnum $category,
    ) {}
}
