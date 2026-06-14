<?php

declare(strict_types=1);

namespace App\Data\RecurringInvoices;

use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RecurringInvoiceIndexFilterData extends Data
{
    public function __construct(
        #[Nullable, Max(255)]
        public ?string $search = null,
        #[Nullable]
        public ?RecurringInvoiceStatusEnum $status = null,
        #[Nullable]
        public ?RecurringFrequencyEnum $frequency = null,
        #[Nullable]
        public ?string $client_id = null,
        #[Nullable, Min(1), Max(100)]
        public int $per_page = 15,
    ) {}
}
