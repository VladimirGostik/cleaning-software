<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum EfaStatusEnum: string
{
    case None = 'none';
    case Ready = 'ready';
    case Generated = 'generated';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return __('app.efa_status_'.$this->value);
    }

    public function canSubmit(): bool
    {
        return in_array($this, [self::None, self::Ready, self::Generated, self::Rejected, self::Failed], true);
    }
}
