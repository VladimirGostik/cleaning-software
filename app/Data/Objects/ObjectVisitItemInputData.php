<?php

declare(strict_types=1);

namespace App\Data\Objects;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Attributes\WithoutValidation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ObjectVisitItemInputData extends Data
{
    public function __construct(
        #[Nullable, Uuid]
        public readonly ?string $id,
        #[Required, Max(255)]
        public readonly string $name,
        #[WithoutValidation, LiteralTypeScriptType('string | number')]
        public readonly string|int|float $price,
    ) {}
}
