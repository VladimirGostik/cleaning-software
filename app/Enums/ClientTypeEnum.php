<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ClientTypeEnum: string
{
    case Corporate = 'corporate';
    case Private = 'private';

    public function label(): string
    {
        return __('app.client_type_'.$this->value);
    }
}
