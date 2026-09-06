<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ContractCategoryEnum: string
{
    case ServiceAgreement = 'service_agreement';
    case Employment = 'employment';
    case Nda = 'nda';
    case Gdpr = 'gdpr';
    case Other = 'other';

    public function label(): string
    {
        return __('app.contract_category_'.$this->value);
    }

    /** Expected `contractable_type` for this category, or null when either is allowed. */
    public function expectedContractableType(): ?ContractableTypeEnum
    {
        return match ($this) {
            self::Employment => ContractableTypeEnum::TenantMembership,
            self::ServiceAgreement => ContractableTypeEnum::CleaningObject,
            self::Nda, self::Gdpr, self::Other => null,
        };
    }
}
