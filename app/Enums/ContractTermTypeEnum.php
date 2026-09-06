<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ContractTermTypeEnum: string
{
    case Fixed = 'fixed';
    case Indefinite = 'indefinite';

    public function label(): string
    {
        return __('app.contract_term_type_'.$this->value);
    }
}
