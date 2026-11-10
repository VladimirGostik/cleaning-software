<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Data\Auth\MobileCompanyData;
use App\Enums\ExpenseCategoryEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseFormContextData extends Data
{
    /**
     * @param  list<MobileCompanyData>  $companies
     * @param  list<ExpenseCategoryEnum>  $categories
     * @param  list<ExpenseEmployeeOptionData>  $employees
     */
    public function __construct(
        public readonly array $companies,
        public readonly array $categories,
        public readonly array $employees = [],
    ) {}
}
