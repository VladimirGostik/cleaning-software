<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\EmploymentContractTypeEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractCreatePrefillData extends Data
{
    public function __construct(
        public readonly ?ContractableTypeEnum $contractable_type,
        public readonly ?string $contractable_id,
        public readonly ?string $contractable_label,
        public readonly ?ContractCategoryEnum $category,
        public readonly ?EmploymentContractTypeEnum $employment_type,
        public readonly ?string $document_template_id,
    ) {}
}
