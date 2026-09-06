<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

use App\Enums\TenantColorEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CompanyOverviewData extends Data
{
    public function __construct(
        public readonly string $tenant_id,
        public readonly string $name,
        public readonly ?TenantColorEnum $color,
        public readonly bool $is_current,
        public readonly bool $supplier_complete,
        public readonly ?InvoiceMetricsData $invoices,
        public readonly ?ScheduleMetricsData $schedule,
        public readonly ?ContractsMetricsData $contracts,
        public readonly ?PeopleMetricsData $people,
    ) {}
}
