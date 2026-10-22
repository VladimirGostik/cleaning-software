<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\PaymentTypeEnum;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoicePaymentData extends Data
{
    public function __construct(
        #[Required, Date, BeforeOrEqual('today')]
        public readonly string $paid_on,
        #[Required, Numeric, Rule(['gt:0', 'decimal:0,2'])]
        public readonly float $amount,
        public readonly PaymentTypeEnum $payment_type,
        #[Nullable, Max(500)]
        public readonly ?string $note = null,
    ) {}
}
