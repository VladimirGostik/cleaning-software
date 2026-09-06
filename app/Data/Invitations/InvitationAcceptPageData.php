<?php

declare(strict_types=1);

namespace App\Data\Invitations;

use App\Enums\InvitationAcceptStateEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvitationAcceptPageData extends Data
{
    public function __construct(
        public readonly InvitationAcceptStateEnum $state,
        public readonly string $token,
        public readonly ?string $email,
        public readonly ?string $tenant_name,
        public readonly ?string $role_name,
        public readonly ?string $invited_email,
    ) {}
}
