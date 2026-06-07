<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ChecksFeatures;
use App\Data\Tenants\AddTenantData;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TenantController extends Controller
{
    public function store(AddTenantData $data, Request $request, RegistrationService $service, ChecksFeatures $checker): RedirectResponse
    {
        abort_unless($checker->canCreateTenant($request->user()), 403, __('app.tenant.limit_reached'));

        $tenant = $service->addTenant($request->user(), $data);

        session(['active_tenant_id' => $tenant->id]);

        return to_route('dashboard')->with('flash.success', __('app.tenant.created'));
    }
}
