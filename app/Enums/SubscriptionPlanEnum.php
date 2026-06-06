<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum SubscriptionPlanEnum: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return __('app.subscription_plan.' . $this->value);
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
}
