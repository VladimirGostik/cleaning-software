<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ExpenseSourceEnum: string
{
    case Manual = 'manual';
    case Receipt = 'receipt';
    case BankStatement = 'bank_statement';
    case Mcp = 'mcp';

    public function label(): string
    {
        return __('app.expense_source_'.$this->value);
    }
}
