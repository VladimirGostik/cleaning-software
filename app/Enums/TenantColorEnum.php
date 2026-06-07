<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TenantColorEnum: string
{
    case Amber700 = '#A16207';
    case Amber600 = '#D97706';
    case Blue600 = '#2563EB';
    case Indigo600 = '#4F46E5';
    case Teal600 = '#0D9488';
    case Emerald600 = '#059669';
    case Violet600 = '#7C3AED';
    case Slate600 = '#475569';

    public function label(): string
    {
        return __('app.tenant_color.' . strtolower(str_replace('#', '', $this->value)));
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
