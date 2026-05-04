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
        public ?string $id,
        #[Required, Max(255)]
        public string $name,
        #[Nullable, Max(255)]
        public ?string $position,
        #[Nullable, Email, Max(255)]
        public ?string $email,
        #[Nullable, Max(64)]
        public ?string $phone,
        public bool $is_primary = false,
    ) {}
}
