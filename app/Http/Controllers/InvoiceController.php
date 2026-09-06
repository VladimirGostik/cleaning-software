<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\GeneratesPaymentQr;
use App\Contracts\RendersInvoicePdf;
use App\Data\Clients\ClientOptionData;
use App\Data\Invoices\InvoiceDetailData;
use App\Data\Invoices\InvoiceFormContextData;
use App\Data\Invoices\InvoiceIssueData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Data\Objects\ObjectOptionData;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Navigation\NavItem;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Builder;
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
    public function show(Invoice $invoice, GeneratesPaymentQr $qr): InertiaResponse
    {
        $invoice->load(['items', 'client']);

        return Inertia::render('Invoices/Show', [
            'invoice' => InvoiceDetailData::fromModel($invoice, $qr->dataUri($invoice)),
        ]);
    }

    #[Authorize('update', 'invoice')]
    public function edit(Invoice $invoice): InertiaResponse
    {
        $invoice->load(['items', 'client']);

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
    public function issue(InvoiceIssueData $data, Invoice $invoice): RedirectResponse
    {
        $this->invoices->issue($invoice, $data);

        return to_route('invoices.show', $invoice)->with('success', __('app.invoice_issued'));
    }

    #[Authorize('markPaid', 'invoice')]
    public function pay(Invoice $invoice): RedirectResponse
    {
        $this->invoices->markPaid($invoice);

        return to_route('invoices.show', $invoice)->with('success', __('app.invoice_paid'));
    }

    #[Authorize('cancel', 'invoice')]
    public function cancel(Invoice $invoice): RedirectResponse
    {
        $this->invoices->cancel($invoice);

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

    /** @return array<int, ClientOptionData> */
    private function clientOptions(): array
    {
        return Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => ClientOptionData::fromModel($client))
            ->all();
    }

    /** @return array<int, ObjectOptionData> */
    private function objectOptions(?string $keepObjectId = null): array
    {
        return CleaningObject::query()
            ->where(function (Builder $query) use ($keepObjectId): void {
                $query->where('is_active', true);

                if ($keepObjectId !== null) {
                    $query->orWhere('id', $keepObjectId);
                }
            })
            ->orderBy('name')
            ->get()
            ->map(fn (CleaningObject $object) => ObjectOptionData::fromModel($object))
            ->all();
    }
}
