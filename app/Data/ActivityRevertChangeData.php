<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ActivityRevertChangeData extends Data
{
    public function __construct(
        public readonly string $field,
        public readonly string $label,
        public readonly mixed $old,
        public readonly mixed $new,
        public readonly mixed $current,
        public readonly bool $conflict,
        public readonly bool $masked,
    ) {}
}
