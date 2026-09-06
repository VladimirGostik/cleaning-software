<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Clients\ClientOptionData;
use App\Data\Invoices\InvoiceFormContextData;
use App\Data\Invoices\InvoiceListItemData;
use App\Data\Objects\ObjectOptionData;
use App\Data\RecurringInvoices\RecurringInvoiceDetailData;
use App\Data\RecurringInvoices\RecurringInvoiceUpsertData;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Tenant;
use App\Navigation\NavItem;
use App\Services\RecurringInvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class RecurringInvoiceController extends Controller
{
    public function __construct(private readonly RecurringInvoiceService $service) {}

    #[Authorize('viewAny', RecurringInvoice::class)]
    #[NavItem(label: 'app.recurring_invoices', route: 'recurring-invoices.index', icon: 'ArrowPathIcon', permission: PermissionEnum::ViewRecurringInvoices->value, order: 41)]
    public function index(Request $request): InertiaResponse
    {
        return Inertia::render('RecurringInvoices/Index', [
            'recurringInvoices' => $this->service->paginate($request),
            'filters' => $request->query(),
            'filterOptions' => [
                'clients' => $this->clientOptions(),
            ],
        ]);
    }

    #[Authorize('create', RecurringInvoice::class)]
    public function create(): InertiaResponse
    {
        $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

        return Inertia::render('RecurringInvoices/Create', [
            'context' => InvoiceFormContextData::fromTenant($tenant, $this->clientOptions(), $this->objectOptions()),
        ]);
    }

    #[Authorize('create', RecurringInvoice::class)]
    public function store(RecurringInvoiceUpsertData $data): RedirectResponse
    {
        $ri = $this->service->create($data);

        return to_route('recurring-invoices.show', $ri)->with('success', __('app.recurring_invoice_created'));
    }

    #[Authorize('view', 'recurringInvoice')]
    public function show(RecurringInvoice $recurringInvoice): InertiaResponse
    {
        /** @var Collection<int, Invoice> $generatedInvoices */
        $generatedInvoices = $recurringInvoice->generatedInvoices()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('RecurringInvoices/Show', [
            'recurringInvoice' => RecurringInvoiceDetailData::fromModel($recurringInvoice),
            'generatedInvoices' => $generatedInvoices
                ->map(fn (Invoice $invoice) => InvoiceListItemData::fromModel($invoice))
                ->all(),
        ]);
    }

    #[Authorize('update', 'recurringInvoice')]
    public function edit(RecurringInvoice $recurringInvoice): InertiaResponse
    {
        $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

        return Inertia::render('RecurringInvoices/Edit', [
            'recurringInvoice' => RecurringInvoiceDetailData::fromModel($recurringInvoice),
            'context' => InvoiceFormContextData::fromTenant(
                $tenant,
                $this->clientOptions(),
                $this->objectOptions($recurringInvoice->cleaning_object_id),
            ),
        ]);
    }

    #[Authorize('update', 'recurringInvoice')]
    public function update(RecurringInvoiceUpsertData $data, RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->service->update($recurringInvoice, $data);

        return to_route('recurring-invoices.show', $recurringInvoice)->with('success', __('app.recurring_invoice_updated'));
    }

    #[Authorize('delete', 'recurringInvoice')]
    public function destroy(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->service->delete($recurringInvoice);

        return to_route('recurring-invoices.index')->with('success', __('app.recurring_invoice_deleted'));
    }

    #[Authorize('pause', 'recurringInvoice')]
    public function pause(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->service->pause($recurringInvoice);

        return to_route('recurring-invoices.show', $recurringInvoice)->with('success', __('app.recurring_invoice_paused'));
    }

    #[Authorize('resume', 'recurringInvoice')]
    public function resume(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->service->resume($recurringInvoice);

        return to_route('recurring-invoices.show', $recurringInvoice)->with('success', __('app.recurring_invoice_resumed'));
    }

    #[Authorize('cancel', 'recurringInvoice')]
    public function cancel(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->service->cancel($recurringInvoice);

        return to_route('recurring-invoices.show', $recurringInvoice)->with('success', __('app.recurring_invoice_cancelled'));
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
