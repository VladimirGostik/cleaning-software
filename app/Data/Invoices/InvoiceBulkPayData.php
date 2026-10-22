<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceBulkPayData extends Data
{
    /** @param list<string> $ids */
    public function __construct(
        public readonly array $ids,
        #[Required, Date, BeforeOrEqual('today')]
        public readonly string $paid_on,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return InvoiceBulkActionData::rules();
    }
}
