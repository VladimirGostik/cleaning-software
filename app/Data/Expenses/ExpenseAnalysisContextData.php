<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Data;

final class ExpenseAnalysisContextData extends Data
{
    /** @param  list<ExpenseCompanyContextData>  $companies */
    public function __construct(
        public readonly array $companies,
        public readonly string $today,
    ) {}
}
