<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum InvitationAcceptStateEnum: string
{
    case Expired = 'expired';
    case WrongUser = 'wrong_user';
    case ExistingUser = 'existing_user';
    case NewUser = 'new_user';
}
