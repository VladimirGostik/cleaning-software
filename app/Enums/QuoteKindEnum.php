<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum QuoteKindEnum: string
{
    case Itemized = 'itemized';
    case Document = 'document';

    public function label(): string
    {
        return __('app.quote_kind_'.$this->value);
    }
}
