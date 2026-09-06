<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DashboardData extends Data
{
    public function __construct(
        /** @var CompanyOverviewData[] */
        #[DataCollectionOf(CompanyOverviewData::class)]
        public readonly array $companies,
        /** @var DashboardAlertData[] */
        #[DataCollectionOf(DashboardAlertData::class)]
        public readonly array $alerts,
        /** @var DashboardAlertCountData[] */
        #[DataCollectionOf(DashboardAlertCountData::class)]
        public readonly array $alert_counts,
        public readonly string $generated_at,
    ) {}
}
