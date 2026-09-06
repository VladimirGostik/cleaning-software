<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Tenants\AddTenantData;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * `store` is deliberately not policy-gated: a brand-new tenant has no roles yet,
 * so any permission check on tenant creation would be circular (D4a). Any
 * authenticated user may create a tenant they then own.
 */
final class TenantController extends Controller
{
    public function store(AddTenantData $data, Request $request, RegistrationService $service): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tenant = $service->addTenant($user, $data);

        session(['active_tenant_id' => $tenant->id]);

        return to_route('settings.invoicing')->with('success', __('app.tenant_created_complete_supplier'));
    }

    #[Authorize('switchTo', 'tenant')]
    public function switch(Tenant $tenant, Request $request): RedirectResponse
    {
        session(['active_tenant_id' => $tenant->id]);

        return to_route('dashboard')->with('success', __('app.tenant_switched'));
    }
}
