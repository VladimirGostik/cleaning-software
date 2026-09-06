<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractsMetricsData extends Data
{
    public function __construct(
        public readonly ?int $active,
        public readonly ?int $expiring_30d,
        public readonly ?int $quotes_awaiting,
    ) {}
}
