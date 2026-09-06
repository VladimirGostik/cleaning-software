<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PeopleMetricsData extends Data
{
    public function __construct(
        public readonly ?int $employees,
        public readonly ?int $clients,
        public readonly ?int $objects,
    ) {}
}
