<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class WorkRecordItemOptionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}
}
