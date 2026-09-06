<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

use App\Enums\DashboardAlertTypeEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DashboardAlertCountData extends Data
{
    public function __construct(
        public readonly DashboardAlertTypeEnum $type,
        public readonly int $count,
    ) {}
}
