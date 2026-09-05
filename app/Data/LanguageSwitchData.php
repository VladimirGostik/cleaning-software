<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class LanguageSwitchData extends Data
{
    public function __construct(
        #[Required, In(['sk', 'en'])]
        public readonly string $locale,
    ) {}
}
