<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Invoices\InvoiceSettingsData;
use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RoundingModeEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceSettingsService;
use App\Services\Pdf\InvoicePreviewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class InvoiceSettingsController extends Controller
{
    public function __construct(private readonly InvoiceSettingsService $settings) {}

    #[Authorize('manage billing settings')]
    public function show(): InertiaResponse
    {
        $tenantId = app('current_tenant_id');

        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->with('interface')->findOrFail($tenantId);

        return Inertia::render('Settings/Invoicing', [
            'settings' => InvoiceSettingsData::fromTenant($tenant),
            'templates' => InvoiceTemplateEnum::options(),
            'recurringStateOptions' => RecurringDefaultStateEnum::options(),
            'paymentTypeOptions' => PaymentTypeEnum::options(),
            'currencyOptions' => CurrencyEnum::options(),
            'roundingModeOptions' => RoundingModeEnum::options(),
        ]);
    }

    #[Authorize('manage billing settings')]
    public function update(InvoiceSettingsData $data): RedirectResponse
    {
        $tenantId = app('current_tenant_id');

        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->with('interface')->findOrFail($tenantId);

        $this->settings->update($tenant, $data);

        return to_route('invoices.index')->with('flash.success', __('app.invoice_settings.saved'));
    }

    #[Authorize('viewAny', Invoice::class)]
    public function preview(InvoiceTemplateEnum $template): Response
    {
        $invoice = InvoicePreviewData::make($template);
        $html = view($template->view(), ['invoice' => $invoice, 'qrDataUri' => null])->render();

        return response($html)->header('Content-Type', 'text/html');
    }
}
