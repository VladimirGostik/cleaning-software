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
        return __('app.contract_status_'.$this->value);
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => $to === self::Active,
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
