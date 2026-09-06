<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum InvoiceStatusEnum: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('app.invoice_status_'.$this->value);
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Issued], true),
            self::Issued => in_array($to, [self::Paid, self::Overdue, self::Cancelled], true),
            self::Overdue => in_array($to, [self::Paid, self::Cancelled], true),
            self::Paid, self::Cancelled => false,
        };
    }
}
