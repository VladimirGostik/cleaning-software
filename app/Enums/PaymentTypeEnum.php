<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PaymentTypeEnum: string
{
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Card = 'card';
    case CashOnDelivery = 'cod';
    case Other = 'other';

    public function label(): string
    {
        return __('app.payment_type_'.$this->value);
    }
}
