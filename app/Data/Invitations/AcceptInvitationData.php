<?php

declare(strict_types=1);

namespace App\Data\Invitations;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AcceptInvitationData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $password,
        #[Nullable, Max(255)]
        public ?string $name = null,
    ) {}
}
