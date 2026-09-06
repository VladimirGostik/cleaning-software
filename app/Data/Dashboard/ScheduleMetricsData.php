<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ScheduleMetricsData extends Data
{
    public function __construct(
        public readonly int $today,
        public readonly int $this_week,
        public readonly ?int $unassigned_next_7_days,
        public readonly bool $own_only,
    ) {}
}
