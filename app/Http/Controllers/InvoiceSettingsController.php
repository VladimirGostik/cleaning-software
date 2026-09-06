<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Invoices\InvoiceSettingsData;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\PermissionEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Navigation\NavItem;
use App\Services\InvoiceSettingsService;
use App\Services\Pdf\InvoicePreviewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class InvoiceSettingsController extends Controller
{
    public function __construct(private readonly InvoiceSettingsService $settings) {}

    #[Authorize(PermissionEnum::ManageBillingSettings->value)]
    #[NavItem(label: 'app.invoicing_settings', route: 'settings.invoicing', icon: 'BanknotesIcon', permission: PermissionEnum::ManageBillingSettings->value, group: 'settings', order: 20)]
    public function show(): InertiaResponse
    {
        $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

        return Inertia::render('Settings/Invoicing', [
            'settings' => InvoiceSettingsData::fromTenant($tenant),
        ]);
    }

    #[Authorize(PermissionEnum::ManageBillingSettings->value)]
    public function update(InvoiceSettingsData $data): RedirectResponse
    {
        $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

        $this->settings->update($tenant, $data);

        return to_route('settings.invoicing')->with('success', __('app.invoice_settings_saved'));
    }

    #[Authorize('viewAny', Invoice::class)]
    public function preview(InvoiceTemplateEnum $template): Response
    {
        $invoice = InvoicePreviewData::make($template);
        $html = View::make($template->view(), ['invoice' => $invoice, 'qrDataUri' => null])->render();

        return response($html)->header('Content-Type', 'text/html');
    }
}
