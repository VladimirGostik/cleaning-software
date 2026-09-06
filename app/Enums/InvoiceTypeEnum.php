<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum InvoiceTypeEnum: string
{
    case Monthly = 'monthly';
    case OneOff = 'one_off';
    case Special = 'special';

    public function label(): string
    {
        return __('app.invoice_type_'.$this->value);
    }
}
