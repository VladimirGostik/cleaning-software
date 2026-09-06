<?php

declare(strict_types=1);

namespace App\Data\Clients;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ClientContactData extends Data
{
    public function __construct(
        public readonly ?string $id,
        #[Required, Max(255)]
        public readonly string $name,
        #[Nullable, Max(255)]
        public readonly ?string $position,
        #[Nullable, Email, Max(255)]
        public readonly ?string $email,
        #[Nullable, Max(64)]
        public readonly ?string $phone,
        public readonly bool $is_primary = false,
    ) {}
}
