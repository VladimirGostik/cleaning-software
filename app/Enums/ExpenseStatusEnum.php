<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ExpenseStatusEnum: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return __('app.expense_status_'.$this->value);
    }
}
