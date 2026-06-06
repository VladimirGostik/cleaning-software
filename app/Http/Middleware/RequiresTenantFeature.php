<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\ChecksFeatures;
use App\Enums\FeatureEnum;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class RequiresTenantFeature
{
    public function __construct(private ChecksFeatures $checker) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        if ($tenantId === null) {
            abort(403, __('app.feature.locked'));
        }

        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            abort(403, __('app.feature.locked'));
        }

        $featureEnum = FeatureEnum::tryFrom($feature);

        if ($featureEnum === null) {
            if (app()->isProduction()) {
                abort(403, __('app.feature.locked'));
            }

            throw new RuntimeException("Unknown feature: {$feature}");
        }

        if (! $this->checker->hasFeature($tenant, $featureEnum)) {
            abort(403, __('app.feature.locked'));
        }

        return $next($request);
    }
}
