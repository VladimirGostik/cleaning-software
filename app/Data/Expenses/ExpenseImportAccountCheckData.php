<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\ExpenseImportAccountStatusEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseImportAccountCheckData extends Data
{
    public function __construct(
        public readonly ExpenseImportAccountStatusEnum $status,
        public readonly string $statement_iban,
        public readonly ?string $owner_tenant_id = null,
        public readonly ?string $owner_tenant_name = null,
    ) {}
}
