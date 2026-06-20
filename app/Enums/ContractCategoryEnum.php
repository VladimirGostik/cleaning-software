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
        return __('app.contract_category.' . $this->value);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * Returns which contractable_type morph alias is expected for this category (null = any).
     *
     * @return string|null morph alias ('cleaning_object'|'tenant_membership'|null)
     */
    public function expectedContractableType(): ?string
    {
        return match ($this) {
            self::Employment => 'tenant_membership',
            self::ServiceAgreement, self::Nda, self::Gdpr => 'cleaning_object',
            self::Other => null,
        };
    }
}
