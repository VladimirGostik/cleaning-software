<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DevicePlatformEnum: string
{
    case Android = 'android';
    case Ios = 'ios';
}
