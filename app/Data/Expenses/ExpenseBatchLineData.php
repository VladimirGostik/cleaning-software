<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseBatchLineData extends Data
{
    public function __construct(
        #[Required]
        public readonly string $amount,
        #[Nullable, Uuid]
        public readonly ?string $tenant_membership_id = null,
        #[Nullable, Max(200)]
        public readonly ?string $supplier_name = null,
        #[Nullable, Max(5000)]
        public readonly ?string $description = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
        ];
    }
}
