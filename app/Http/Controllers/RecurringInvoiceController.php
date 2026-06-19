<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Clients\ClientOptionData;
use App\Data\Invoices\InvoiceListItemData;
use App\Data\Objects\ObjectOptionData;
use App\Data\RecurringInvoices\RecurringInvoiceDetailData;
use App\Data\RecurringInvoices\RecurringInvoiceIndexFilterData;
use App\Data\RecurringInvoices\RecurringInvoiceListItemData;
use App\Data\RecurringInvoices\RecurringInvoiceUpsertData;
use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Enums\RoundingModeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Tenant;
use App\Services\RecurringInvoiceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

final class RecurringInvoiceController extends Controller
{
    public function __construct(private readonly RecurringInvoiceService $service) {}

    #[Authorize('viewAny', RecurringInvoice::class)]
    public function index(RecurringInvoiceIndexFilterData $filter): InertiaResponse
    {
        $paginator = $this->service->paginate($filter);

        return Inertia::render('RecurringInvoices/Index', [
            'recurringInvoices' => RecurringInvoiceListItemData::collect(
                $paginator->through(fn (RecurringInvoice $ri) => RecurringInvoiceListItemData::fromModel($ri)),
                PaginatedDataCollection::class,
            ),
            'filters' => $filter,
            'statusOptions' => RecurringInvoiceStatusEnum::options(),
            'frequencyOptions' => RecurringFrequencyEnum::options(),
            'clients' => ClientOptionData::collect(
                Client::query()->select(['id', 'name'])->orderBy('name')->get(),
                DataCollection::class,
            ),
        ]);
    }

    #[Authorize('create', RecurringInvoice::class)]
    public function create(): InertiaResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->with('interface')->findOrFail(app('current_tenant_id'));
        $interface = $tenant->interface;

        return Inertia::render('RecurringInvoices/Create', [
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
            'frequencyOptions' => RecurringFrequencyEnum::options(),
            'statusOptions' => RecurringInvoiceStatusEnum::options(),
            'recurringStateOptions' => RecurringDefaultStateEnum::options(),
            'recurringDefaultState' => $interface?->recurring_default_state ?? RecurringDefaultStateEnum::Draft,
            'isVatPayer' => $tenant->is_vat_payer,
            'vatRate' => $tenant->vat_rate,
            'vatRateOptions' => [
                ['value' => 23, 'label' => '23%'],
                ['value' => 19, 'label' => '19%'],
                ['value' => 5, 'label' => '5%'],
                ['value' => 0, 'label' => '0%'],
            ],
            'paymentTypeOptions' => PaymentTypeEnum::options(),
            'currencyOptions' => CurrencyEnum::options(),
            'roundingModeOptions' => RoundingModeEnum::options(),
            'invoiceDefaults' => [
                'constant_symbol' => $interface?->default_constant_symbol,
                'payment_type' => ($interface?->default_payment_type ?? PaymentTypeEnum::Transfer)->value,
                'currency' => ($interface?->default_currency ?? CurrencyEnum::EUR)->value,
                'rounding_mode' => ($interface?->default_rounding_mode ?? RoundingModeEnum::None)->value,
            ],
        ]);
    }

    #[Authorize('create', RecurringInvoice::class)]
    public function store(RecurringInvoiceUpsertData $data): RedirectResponse
    {
        $ri = $this->service->create($data);

        return to_route('recurring-invoices.show', $ri)->with('flash.success', __('app.recurring_invoices.created'));
    }

    #[Authorize('view', 'recurringInvoice')]
    public function show(RecurringInvoice $recurringInvoice): InertiaResponse
    {
        $recurringInvoice->loadMissing('items');

        /** @var Collection<int, Invoice> $generatedInvoices */
        $generatedInvoices = $recurringInvoice->generatedInvoices()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('RecurringInvoices/Show', [
            'recurringInvoice' => RecurringInvoiceDetailData::fromModel($recurringInvoice),
            'generatedInvoices' => InvoiceListItemData::collect(
                $generatedInvoices->map(fn (Invoice $inv) => InvoiceListItemData::fromModel($inv)),
                DataCollection::class,
            ),
        ]);
    }

    #[Authorize('update', 'recurringInvoice')]
    public function edit(RecurringInvoice $recurringInvoice): InertiaResponse
    {
        $recurringInvoice->loadMissing('items');

        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->with('interface')->findOrFail(app('current_tenant_id'));
        $interface = $tenant->interface;

        return Inertia::render('RecurringInvoices/Edit', [
            'recurringInvoice' => RecurringInvoiceDetailData::fromModel($recurringInvoice),
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
            'frequencyOptions' => RecurringFrequencyEnum::options(),
            'statusOptions' => RecurringInvoiceStatusEnum::options(),
            'recurringStateOptions' => RecurringDefaultStateEnum::options(),
            'recurringDefaultState' => $interface?->recurring_default_state ?? RecurringDefaultStateEnum::Draft,
            'isVatPayer' => $tenant->is_vat_payer,
            'vatRate' => $tenant->vat_rate,
            'vatRateOptions' => [
                ['value' => 23, 'label' => '23%'],
                ['value' => 19, 'label' => '19%'],
                ['value' => 5, 'label' => '5%'],
                ['value' => 0, 'label' => '0%'],
            ],
            'paymentTypeOptions' => PaymentTypeEnum::options(),
            'currencyOptions' => CurrencyEnum::options(),
            'roundingModeOptions' => RoundingModeEnum::options(),
            'invoiceDefaults' => [
                'constant_symbol' => $interface?->default_constant_symbol,
                'payment_type' => ($interface?->default_payment_type ?? PaymentTypeEnum::Transfer)->value,
                'currency' => ($interface?->default_currency ?? CurrencyEnum::EUR)->value,
                'rounding_mode' => ($interface?->default_rounding_mode ?? RoundingModeEnum::None)->value,
            ],
        ]);
    }

    #[Authorize('update', 'recurringInvoice')]
    public function update(RecurringInvoiceUpsertData $data, RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->service->update($recurringInvoice, $data);

        return to_route('recurring-invoices.show', $recurringInvoice)->with('flash.success', __('app.recurring_invoices.updated'));
    }

    #[Authorize('delete', 'recurringInvoice')]
    public function destroy(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->service->delete($recurringInvoice);

        return to_route('recurring-invoices.index')->with('flash.success', __('app.recurring_invoices.deleted'));
    }

    #[Authorize('pause', 'recurringInvoice')]
    public function pause(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->service->pause($recurringInvoice);

        return to_route('recurring-invoices.show', $recurringInvoice)->with('flash.success', __('app.recurring_invoices.paused'));
    }

    #[Authorize('resume', 'recurringInvoice')]
    public function resume(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->service->resume($recurringInvoice);

        return to_route('recurring-invoices.show', $recurringInvoice)->with('flash.success', __('app.recurring_invoices.resumed'));
    }

    #[Authorize('cancel', 'recurringInvoice')]
    public function cancel(RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $this->service->cancel($recurringInvoice);

        return to_route('recurring-invoices.show', $recurringInvoice)->with('flash.success', __('app.recurring_invoices.cancelled'));
    }
}
