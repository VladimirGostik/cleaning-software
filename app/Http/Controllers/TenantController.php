<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Tenants\AddTenantData;
use App\Data\Tenants\TenantSwitchData;
use App\Models\Tenant;
use App\Models\User;
use App\Navigation\NavigationRegistry;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class TenantController extends Controller
{
    #[Authorize('create', Tenant::class)]
    public function store(AddTenantData $data, Request $request, RegistrationService $service): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tenant = $service->addTenant($user, $data);

        session(['active_tenant_id' => $tenant->id]);

        if ($tenant->hasCompleteSupplierProfile()) {
            return to_route('dashboard')->with('success', __('app.tenant_created'));
        }

        return to_route('settings.invoicing')->with('success', __('app.tenant_created_complete_supplier'));
    }

    #[Authorize('switchTo', 'tenant')]
    public function switch(Tenant $tenant, TenantSwitchData $data, Request $request, NavigationRegistry $navigation): RedirectResponse
    {
        session(['active_tenant_id' => $tenant->id]);

        if ($data->redirect_to !== null) {
            /** @var User $user */
            $user = $request->user();

            $target = $data->keep_module ? $navigation->landingFor($user, $tenant->id, $data->redirect_to) : $data->redirect_to;

            return redirect($target)->with('success', __('app.tenant_switched'));
        }

        return to_route('dashboard')->with('success', __('app.tenant_switched'));
    }
}
