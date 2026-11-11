<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ContractSourceEnum: string
{
    case Generated = 'generated';
    case File = 'file';

    public function label(): string
    {
        return __('app.contract_source_'.$this->value);
    }
}
