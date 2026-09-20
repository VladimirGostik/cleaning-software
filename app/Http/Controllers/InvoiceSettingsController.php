<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ResolvesSupplierSignature;
use App\Data\Invoices\InvoiceSettingsData;
use App\Data\Invoices\InvoiceSignatureConstraintsData;
use App\Data\Tenants\TenantSignatureData;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\PermissionEnum;
use App\Models\Tenant;
use App\Navigation\NavItem;
use App\Services\InvoiceSettingsService;
use App\Services\Pdf\InvoicePreviewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Storage;
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
            'signature' => TenantSignatureData::fromTenant($tenant),
            'signature_constraints' => InvoiceSignatureConstraintsData::fromConfig(),
        ]);
    }

    #[Authorize(PermissionEnum::ManageBillingSettings->value)]
    public function update(InvoiceSettingsData $data, Request $request): RedirectResponse
    {
        $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

        $this->settings->update($tenant, $data, $request->user(), $request->session()->getId());

        return to_route('settings.invoicing')->with('success', __('app.invoice_settings_saved'));
    }

    #[Authorize(PermissionEnum::ManageBillingSettings->value)]
    public function signature(): Response
    {
        $tenant = Tenant::query()->findOrFail(current_tenant_id());
        $media = $tenant->signatureMedia;

        if ($media === null) {
            abort(404);
        }

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        if (! $disk->exists($path)) {
            abort(404);
        }

        return response($disk->get($path), 200, [
            'Content-Type' => $media->mime_type ?? 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    #[Authorize(PermissionEnum::ManageBillingSettings->value)]
    public function preview(InvoiceTemplateEnum $template, ResolvesSupplierSignature $signatures): Response
    {
        $tenant = Tenant::query()->findOrFail(current_tenant_id());
        $invoice = InvoicePreviewData::make($template);

        $footerView = $template->footerView();
        $footerHtml = $footerView !== null
            ? View::make($footerView, ['invoice' => $invoice, 'preview' => true])->render()
            : null;

        $html = View::make($template->view(), [
            'invoice' => $invoice,
            'qrDataUri' => null,
            'signatureDataUri' => $signatures->forTenant($tenant),
            'footerHtml' => $footerHtml,
        ])->render();

        return response($html)->header('Content-Type', 'text/html');
    }
}
