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

    /** @return list<ContractableTypeEnum> */
    public function allowedContractableTypes(): array
    {
        return match ($this) {
            self::Employment => [ContractableTypeEnum::TenantMembership],
            self::ServiceAgreement => [ContractableTypeEnum::CleaningObject, ContractableTypeEnum::Client],
            self::Nda, self::Gdpr, self::Other => ContractableTypeEnum::cases(),
        };
    }
}
