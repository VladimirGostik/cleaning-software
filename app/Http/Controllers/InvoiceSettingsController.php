<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Invoices\InvoiceSettingsData;
use App\Enums\InvoiceTemplateEnum;
use App\Models\Tenant;
use App\Services\InvoiceSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class InvoiceSettingsController extends Controller
{
    public function __construct(private readonly InvoiceSettingsService $settings) {}

    #[Authorize('manage billing settings')]
    public function show(): Response
    {
        $tenantId = app('current_tenant_id');

        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->with('interface')->findOrFail($tenantId);

        return Inertia::render('Settings/Invoicing', [
            'settings' => InvoiceSettingsData::fromTenant($tenant),
            'templates' => InvoiceTemplateEnum::options(),
        ]);
    }

    #[Authorize('manage billing settings')]
    public function update(InvoiceSettingsData $data): RedirectResponse
    {
        $tenantId = app('current_tenant_id');

        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->with('interface')->findOrFail($tenantId);

        $this->settings->update($tenant, $data);

        return to_route('settings.invoicing')->with('flash.success', __('app.invoice_settings.saved'));
    }
}
