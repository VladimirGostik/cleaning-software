<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RecurringDefaultStateEnum: string
{
    case Draft = 'draft';
    case Issued = 'issued';

    public function label(): string
    {
        return __('app.recurring_default_state_'.$this->value);
    }
}
