<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ObjectTypeEnum: string
{
    case Office = 'office';
    case Apartment = 'apartment';
    case House = 'house';
    case CommonAreas = 'common_areas';

    public function label(): string
    {
        return __('app.object_type.' . $this->value);
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
