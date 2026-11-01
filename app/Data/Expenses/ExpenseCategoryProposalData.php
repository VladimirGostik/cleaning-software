<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\ExpenseCategoryEnum;
use Spatie\LaravelData\Data;

final class ExpenseCategoryProposalData extends Data
{
    public function __construct(
        public readonly int $row,
        public readonly ?ExpenseCategoryEnum $category,
        public readonly float $confidence,
    ) {}
}
