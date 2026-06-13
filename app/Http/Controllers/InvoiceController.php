<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\RendersInvoicePdf;
use App\Data\Clients\ClientOptionData;
use App\Data\Invoices\InvoiceDetailData;
use App\Data\Invoices\InvoiceIndexFilterData;
use App\Data\Invoices\InvoiceIssueData;
use App\Data\Invoices\InvoiceListItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Jobs\SendInvoiceEmail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

final class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    #[Authorize('viewAny', Invoice::class)]
    public function index(InvoiceIndexFilterData $filter): InertiaResponse
    {
        $paginator = $this->invoices->paginate($filter);

        return Inertia::render('Invoices/Index', [
            'invoices' => InvoiceListItemData::collect(
                $paginator->through(fn (Invoice $invoice) => InvoiceListItemData::fromModel($invoice)),
                PaginatedDataCollection::class,
            ),
            'filters' => $filter,
            'statuses' => InvoiceStatusEnum::options(),
            'types' => InvoiceTypeEnum::options(),
        ]);
    }

    #[Authorize('create', Invoice::class)]
    public function create(): InertiaResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->findOrFail(app('current_tenant_id'));

        $clients = ClientOptionData::collect(
            Client::query()->orderBy('name')->get(),
            DataCollection::class,
        );

        return Inertia::render('Invoices/Create', [
            'clients' => $clients,
            'typeOptions' => InvoiceTypeEnum::options(),
            'templateOptions' => InvoiceTemplateEnum::options(),
            'statusOptions' => InvoiceStatusEnum::options(),
            'isVatPayer' => $tenant->is_vat_payer,
            'vatRate' => $tenant->vat_rate,
        ]);
    }

    #[Authorize('create', Invoice::class)]
    public function store(InvoiceUpsertData $data): RedirectResponse
    {
        $invoice = $this->invoices->create($data);

        return to_route('invoices.show', $invoice)->with('flash.success', __('app.invoices.created'));
    }

    #[Authorize('view', 'invoice')]
    public function show(Invoice $invoice): InertiaResponse
    {
        $invoice->loadMissing('items');

        return Inertia::render('Invoices/Show', [
            'invoice' => InvoiceDetailData::fromModel($invoice),
        ]);
    }

    #[Authorize('update', 'invoice')]
    public function edit(Invoice $invoice): InertiaResponse
    {
        $invoice->loadMissing('items');

        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->findOrFail(app('current_tenant_id'));

        $clients = ClientOptionData::collect(
            Client::query()->orderBy('name')->get(),
            DataCollection::class,
        );

        return Inertia::render('Invoices/Edit', [
            'invoice' => InvoiceDetailData::fromModel($invoice),
            'clients' => $clients,
            'typeOptions' => InvoiceTypeEnum::options(),
            'templateOptions' => InvoiceTemplateEnum::options(),
            'statusOptions' => InvoiceStatusEnum::options(),
            'isVatPayer' => $tenant->is_vat_payer,
            'vatRate' => $tenant->vat_rate,
        ]);
    }

    #[Authorize('update', 'invoice')]
    public function update(InvoiceUpsertData $data, Invoice $invoice): RedirectResponse
    {
        $this->invoices->update($invoice, $data);

        return to_route('invoices.show', $invoice)->with('flash.success', __('app.invoices.updated'));
    }

    #[Authorize('delete', 'invoice')]
    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->invoices->delete($invoice);

        return to_route('invoices.index')->with('flash.success', __('app.invoices.deleted'));
    }

    #[Authorize('issue', 'invoice')]
    public function issue(InvoiceIssueData $data, Invoice $invoice): RedirectResponse
    {
        $this->invoices->issue($invoice, $data);

        return to_route('invoices.show', $invoice)->with('flash.success', __('app.invoices.issued'));
    }

    #[Authorize('update', 'invoice')]
    public function pay(Invoice $invoice): RedirectResponse
    {
        $this->invoices->markPaid($invoice);

        return to_route('invoices.show', $invoice)->with('flash.success', __('app.invoices.paid'));
    }

    #[Authorize('cancel', 'invoice')]
    public function cancel(Invoice $invoice): RedirectResponse
    {
        $this->invoices->cancel($invoice);

        return to_route('invoices.show', $invoice)->with('flash.success', __('app.invoices.cancelled'));
    }

    #[Authorize('duplicate', 'invoice')]
    public function duplicate(Invoice $invoice): RedirectResponse
    {
        $newInvoice = $this->invoices->duplicate($invoice);

        return to_route('invoices.edit', $newInvoice)->with('flash.success', __('app.invoices.duplicated'));
    }

    #[Authorize('view', 'invoice')]
    public function pdf(Invoice $invoice, RendersInvoicePdf $pdfService): Response
    {
        $invoice->loadMissing('items');

        $pdfContent = $pdfService->render($invoice);

        $filename = ($invoice->number ?? 'draft') . '.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Authorize('update', 'invoice')]
    public function send(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== InvoiceStatusEnum::Issued) {
            throw ValidationException::withMessages([
                'status' => [__('app.invoices.not_issued_to_send')],
            ]);
        }

        if (empty($invoice->customer_email)) {
            throw ValidationException::withMessages([
                'customer_email' => [__('app.invoices.no_customer_email')],
            ]);
        }

        SendInvoiceEmail::dispatch($invoice->id, $invoice->customer_email)->afterCommit();

        return to_route('invoices.show', $invoice)->with('flash.success', __('app.invoices.send_queued'));
    }
}
