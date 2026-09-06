<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum QuoteStatusEnum: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return __('app.quote_status_'.$this->value);
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Sent, self::Expired], true),
            self::Sent => in_array($to, [self::Accepted, self::Rejected, self::Expired], true),
            self::Accepted, self::Rejected, self::Expired => false,
        };
    }
}
