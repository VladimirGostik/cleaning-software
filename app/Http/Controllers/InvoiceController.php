<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\GeneratesPaymentQr;
use App\Contracts\RendersInvoicePdf;
use App\Data\Invoices\InvoiceBulkLimitsData;
use App\Data\Invoices\InvoiceDetailData;
use App\Data\Invoices\InvoiceFormContextData;
use App\Data\Invoices\InvoiceIssueData;
use App\Data\Invoices\InvoicePaymentData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Concerns\ProvidesSubjectOptions;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Tenant;
use App\Navigation\NavItem;
use App\Services\Efa\EInvoiceService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class InvoiceController extends Controller
{
    use ProvidesSubjectOptions;

    public function __construct(private readonly InvoiceService $invoices) {}

    #[Authorize('viewAny', Invoice::class)]
    #[NavItem(label: 'app.invoices', route: 'invoices.index', icon: 'DocumentTextIcon', permission: PermissionEnum::ViewInvoices->value, order: 40)]
    public function index(Request $request): InertiaResponse
    {
        return Inertia::render('Invoices/Index', [
            'invoices' => $this->invoices->paginate($request),
            'filters' => $request->query(),
            'filterOptions' => [
                'clients' => $this->clientOptions(),
            ],
            'stats' => $this->invoices->stats(),
            'bulkLimits' => InvoiceBulkLimitsData::fromConfig(),
        ]);
    }

    #[Authorize('create', Invoice::class)]
    public function create(): InertiaResponse
    {
        $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

        return Inertia::render('Invoices/Create', [
            'context' => InvoiceFormContextData::fromTenant($tenant, $this->clientOptions(), $this->objectOptions()),
        ]);
    }

    #[Authorize('create', Invoice::class)]
    public function store(InvoiceUpsertData $data): RedirectResponse
    {
        $invoice = $this->invoices->create($data);

        return to_route('invoices.show', $invoice)->with('success', __('app.invoice_created'));
    }

    #[Authorize('view', 'invoice')]
    public function show(Invoice $invoice, GeneratesPaymentQr $qr, EInvoiceService $efa): InertiaResponse
    {
        $invoice->load(['items', 'client', 'tenant', 'quote:id,number']);

        return Inertia::render('Invoices/Show', [
            'invoice' => InvoiceDetailData::fromModel($invoice, $qr->dataUri($invoice), $efa->status($invoice)),
        ]);
    }

    #[Authorize('update', 'invoice')]
    public function edit(Invoice $invoice): InertiaResponse
    {
        $invoice->load(['items', 'client', 'tenant', 'quote:id,number']);

        $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

        return Inertia::render('Invoices/Edit', [
            'invoice' => InvoiceDetailData::fromModel($invoice, null),
            'context' => InvoiceFormContextData::fromTenant(
                $tenant,
                $this->clientOptions(),
                $this->objectOptions($invoice->cleaning_object_id),
            ),
        ]);
    }

    #[Authorize('update', 'invoice')]
    public function update(InvoiceUpsertData $data, Invoice $invoice): RedirectResponse
    {
        $this->invoices->update($invoice, $data);

        return to_route('invoices.show', $invoice)->with('success', __('app.invoice_updated'));
    }

    #[Authorize('delete', 'invoice')]
    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->invoices->delete($invoice);

        return to_route('invoices.index')->with('success', __('app.invoice_deleted'));
    }

    #[Authorize('issue', 'invoice')]
    public function issue(InvoiceIssueData $data, Invoice $invoice, Request $request): RedirectResponse
    {
        $this->invoices->issue($invoice, $data, $request->user());

        return to_route('invoices.show', $invoice)->with('success', __('app.invoice_issued'));
    }

    #[Authorize('recordPayment', 'invoice')]
    public function recordPayment(InvoicePaymentData $data, Invoice $invoice, Request $request): RedirectResponse
    {
        $this->invoices->recordPayment($invoice, $data, $request->user());

        return to_route('invoices.show', $invoice)->with('success', __('app.invoice_payment_recorded'));
    }

    #[Authorize('deletePayment', 'invoice')]
    public function destroyPayment(Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $this->invoices->deletePayment($invoice, $payment);

        return to_route('invoices.show', $invoice)->with('success', __('app.invoice_payment_deleted'));
    }

    #[Authorize('cancel', 'invoice')]
    public function cancel(Invoice $invoice, Request $request): RedirectResponse
    {
        $this->invoices->cancel($invoice, $request->user());

        return to_route('invoices.show', $invoice)->with('success', __('app.invoice_cancelled'));
    }

    #[Authorize('duplicate', 'invoice')]
    public function duplicate(Invoice $invoice): RedirectResponse
    {
        $newInvoice = $this->invoices->duplicate($invoice);

        return to_route('invoices.edit', $newInvoice)->with('success', __('app.invoice_duplicated'));
    }

    #[Authorize('send', 'invoice')]
    public function send(Invoice $invoice): RedirectResponse
    {
        $this->invoices->send($invoice);

        return to_route('invoices.show', $invoice)->with('success', __('app.invoice_send_queued'));
    }

    #[Authorize('downloadPdf', 'invoice')]
    public function pdf(Invoice $invoice, RendersInvoicePdf $pdfService): Response
    {
        $pdfContent = $pdfService->render($invoice);

        $filename = $invoice->pdfFilenameBase().'.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                Str::ascii($filename),
            ),
        ]);
    }
}
