<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Dashboard\CompanyOverviewData;
use App\Data\Dashboard\DashboardAlertData;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('company_overview')]
#[Description('Dashboard metrics and alerts for one company, or for all companies when company_id is omitted. Sections the user may not see are null.')]
#[IsReadOnly]
final class CompanyOverviewTool extends CleanMasterTool
{
    public function handle(Request $request, DashboardService $dashboard, TenantPermissionSnapshot $snapshot): Response
    {
        $companyId = $request->get('company_id');
        $companyId = is_string($companyId) ? $companyId : null;

        return $this->guard($request, $companyId, function (User $user) use ($request, $dashboard, $snapshot, $companyId): Response {
            $request->validate(['company_id' => ['sometimes', 'nullable', 'uuid']]);

            $anchor = $companyId ?? $snapshot->forRequest($user)[0]->tenant->id ?? null;

            if ($anchor === null) {
                throw new AuthorizationException;
            }

            $overview = $this->context->run($user, $anchor, fn () => $dashboard->overviewFor($user));

            $companies = array_values(array_filter(
                $overview->companies,
                fn (CompanyOverviewData $company): bool => $companyId === null || $company->tenant_id === $companyId,
            ));
            $alerts = array_values(array_filter(
                $overview->alerts,
                fn (DashboardAlertData $alert): bool => $companyId === null || $alert->tenant_id === $companyId,
            ));

            return $this->json(['companies' => $companies, 'alerts' => $alerts, 'generated_at' => $overview->generated_at]);
        });
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'company_id' => $schema->string()->format('uuid')->description('Company id from list_companies; omit for every company.'),
        ];
    }
}
