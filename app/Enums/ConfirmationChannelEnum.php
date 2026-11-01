<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ConfirmationChannelEnum: string
{
    case Chat = 'chat';
    case App = 'app';
}
