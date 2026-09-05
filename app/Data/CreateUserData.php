<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CreateUserData extends Data
{
    /** @param array<int, string> $roles */
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        #[Required, Email, Max(255), Rule('unique:users,email')]
        public readonly string $email,
        #[Required, Min(8), Max(255), Confirmed]
        public readonly string $password,
        public readonly string $password_confirmation = '',
        public readonly bool $is_active = true,
        public readonly array $roles = [],
    ) {}
}
