<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NewPasswordData extends Data
{
    public function __construct(
        #[Required]
        public readonly string $token,
        #[Required, Max(255)]
        public readonly string $email,
        #[Required, Min(8), Max(255), Confirmed]
        public readonly string $password,
        public readonly string $password_confirmation = '',
    ) {}
}
