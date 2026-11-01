<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseCanData extends Data
{
    public function __construct(
        public readonly bool $update,
        public readonly bool $delete,
        public readonly bool $confirm,
    ) {}
}
