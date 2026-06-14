<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum RecurringDefaultStateEnum: string
{
    case Draft = 'draft';
    case Issued = 'issued';

    public function label(): string
    {
        return __('app.recurring_invoices.default_state.' . $this->value);
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
