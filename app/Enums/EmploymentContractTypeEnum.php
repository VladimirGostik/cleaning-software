<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum EmploymentContractTypeEnum: string
{
    case Dpp = 'dpp';
    case Dpc = 'dpc';
    case Tpp = 'tpp';
    case SelfEmployed = 'self_employed';

    public function label(): string
    {
        return __('app.employment_type.' . $this->value);
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
