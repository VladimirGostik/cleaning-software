<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ContractStatusEnum: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Terminated = 'terminated';

    public function label(): string
    {
        return __('app.contract_status.' . $this->value);
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

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Active], true),
            self::Active => in_array($to, [self::Expired, self::Terminated], true),
            self::Expired, self::Terminated => false,
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function canBeSigned(): bool
    {
        return $this === self::Draft;
    }

    public function canBeTerminated(): bool
    {
        return $this === self::Active;
    }
}
