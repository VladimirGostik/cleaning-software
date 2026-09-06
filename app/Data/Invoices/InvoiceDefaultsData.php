<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\CurrencyEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceDefaultsData extends Data
{
    public function __construct(
        public readonly ?string $constant_symbol,
        public readonly PaymentTypeEnum $payment_type,
        public readonly CurrencyEnum $currency,
        public readonly RoundingModeEnum $rounding_mode,
    ) {}
}
