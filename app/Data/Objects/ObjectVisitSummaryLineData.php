<?php

declare(strict_types=1);

namespace App\Data\Objects;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ObjectVisitSummaryLineData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $unit_price,
        public readonly int $count,
        public readonly string $total,
    ) {}
}
