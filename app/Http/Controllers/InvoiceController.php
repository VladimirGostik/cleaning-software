<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\RendersInvoicePdf;
use App\Data\Clients\ClientOptionData;
use App\Data\Invoices\BulkInvoiceData;
use App\Data\Invoices\InvoiceDetailData;
use App\Data\Invoices\InvoiceIndexFilterData;
use App\Data\Invoices\InvoiceIssueData;
use App\Data\Invoices\InvoiceListItemData;
use App\Data\Invoices\InvoiceSettingsData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Data\Invoices\TabCountsData;
use App\Data\Objects\ObjectOptionData;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Jobs\SendInvoiceEmail;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    #[Authorize('viewAny', Invoice::class)]
    public function index(InvoiceIndexFilterData $filter): InertiaResponse
    {
        $paginator = $this->invoices->paginate($filter);

        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->with('interface')->findOrFail(app('current_tenant_id'));

        return Inertia::render('Invoices/Index', [
            'invoices' => InvoiceListItemData::collect(
                $paginator->through(fn (Invoice $invoice) => InvoiceListItemData::fromModel($invoice)),
                PaginatedDataCollection::class,
            ),
            'filters' => $filter,
            'statusOptions' => InvoiceStatusEnum::options(),
            'typeOptions' => InvoiceTypeEnum::options(),
            'clients' => ClientOptionData::collect(
                Client::query()->select(['id', 'name'])->orderBy('name')->get(),
                DataCollection::class,
            ),
            'invoiceSettings' => InvoiceSettingsData::fromTenant($tenant),
            'settingsTemplateOptions' => InvoiceTemplateEnum::options(),
            'settingsCompanyName' => $tenant->name,
            'settingsIsVatPayer' => $tenant->is_vat_payer,
            'nextNumberPreview' => null,
            'tabCounts' => TabCountsData::from($this->invoices->tabCounts()),
            'invoiceStats' => $this->invoices->stats(),
        ]);
    }

    #[Authorize('viewAny', Invoice::class)]
    public function export(InvoiceIndexFilterData $filter): StreamedResponse
    {
        $query = $this->invoices->exportQuery($filter);

        $filename = 'invoices-' . now()->format('Y-m-d') . '.csv';

        return response()->stream(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel SK locale compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['number', 'customer_name', 'object_name', 'type', 'issue_date', 'due_date', 'total', 'status']);

            $query->lazy()->each(function (Invoice $invoice) use ($handle): void {
                fputcsv($handle, [
                    $invoice->number ?? '',
                    $invoice->customer_name,
                    $invoice->object_name ?? '',
                    $invoice->type->label(),
                    $invoice->issue_date->toDateString(),
                    $invoice->due_date->toDateString(),
                    $invoice->total,
                    $invoice->status->label(),
                ]);
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Authorize('bulkMarkPaid', Invoice::class)]
    public function bulk(BulkInvoiceData $data): JsonResponse
    {
        $result = $this->invoices->bulkMarkPaid($data->ids);

        return response()->json($result);
    }

    #[Authorize('create', Invoice::class)]
    public function create(): InertiaResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->findOrFail(app('current_tenant_id'));

        return Inertia::render('Invoices/Create', [
            'clients' => ClientOptionData::collect(
                Client::query()->select(['id', 'name'])->orderBy('name')->get(),
                DataCollection::class,
            ),
            'objects' => ObjectOptionData::collect(
                CleaningObject::query()->select(['id', 'name', 'client_id'])->orderBy('name')->get(),
                DataCollection::class,
            ),
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

        return Inertia::render('Invoices/Edit', [
            'invoice' => InvoiceDetailData::fromModel($invoice),
            'clients' => ClientOptionData::collect(
                Client::query()->select(['id', 'name'])->orderBy('name')->get(),
                DataCollection::class,
            ),
            'objects' => ObjectOptionData::collect(
                CleaningObject::query()->select(['id', 'name', 'client_id'])->orderBy('name')->get(),
                DataCollection::class,
            ),
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
