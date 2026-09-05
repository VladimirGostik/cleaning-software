<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\RendersQuotePdf;
use App\Data\Clients\ClientOptionData;
use App\Data\Objects\ObjectOptionData;
use App\Data\Quotes\QuoteAttachClientData;
use App\Data\Quotes\QuoteDetailData;
use App\Data\Quotes\QuoteDocumentUploadData;
use App\Data\Quotes\QuoteIndexFilterData;
use App\Data\Quotes\QuoteListItemData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\CurrencyEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class QuoteController extends Controller
{
    public function __construct(private readonly QuoteService $quotes) {}

    #[Authorize('viewAny', Quote::class)]
    public function index(QuoteIndexFilterData $filter): Response
    {
        $paginator = $this->quotes->paginate($filter);

        return Inertia::render('Quotes/Index', [
            'quotes' => QuoteListItemData::collect(
                $paginator->through(fn (Quote $quote) => QuoteListItemData::fromModel($quote)),
                PaginatedDataCollection::class,
            ),
            'filters' => $filter,
            'statusOptions' => QuoteStatusEnum::options(),
            'clients' => ClientOptionData::collect(
                Client::query()->select(['id', 'name'])->orderBy('name')->get(),
                DataCollection::class,
            ),
        ]);
    }

    #[Authorize('create', Quote::class)]
    public function create(): Response
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->findOrFail(app('current_tenant_id'));

        return Inertia::render('Quotes/Create', [
            'clients' => ClientOptionData::collect(
                Client::query()->select(['id', 'name'])->orderBy('name')->get(),
                DataCollection::class,
            ),
            'objects' => ObjectOptionData::collect(
                CleaningObject::query()->select(['id', 'name', 'client_id'])->where('is_active', true)->orderBy('name')->get(),
                DataCollection::class,
            ),
            'currencyOptions' => CurrencyEnum::options(),
            'kindOptions' => QuoteKindEnum::options(),
            'isVatPayer' => $tenant->is_vat_payer,
            'vatRate' => $tenant->vat_rate,
            'vatRateOptions' => [
                ['value' => 23, 'label' => '23%'],
                ['value' => 19, 'label' => '19%'],
                ['value' => 5, 'label' => '5%'],
                ['value' => 0, 'label' => '0%'],
            ],
        ]);
    }

    #[Authorize('create', Quote::class)]
    public function store(QuoteUpsertData $data): RedirectResponse
    {
        $quote = $this->quotes->create($data);

        return to_route('quotes.show', $quote)
            ->with('flash.success', __('app.quotes.created'));
    }

    #[Authorize('view', 'quote')]
    public function show(Quote $quote): Response
    {
        $isClientless = $quote->client_id === null;

        return Inertia::render('Quotes/Show', [
            'quote' => QuoteDetailData::fromModel($quote),
            'clients' => $isClientless
                ? ClientOptionData::collect(
                    Client::query()->select(['id', 'name'])->orderBy('name')->get(),
                    DataCollection::class,
                )
                : null,
            'objects' => $isClientless
                ? ObjectOptionData::collect(
                    CleaningObject::query()->select(['id', 'name', 'client_id'])->where('is_active', true)->orderBy('name')->get(),
                    DataCollection::class,
                )
                : null,
        ]);
    }

    #[Authorize('update', 'quote')]
    public function edit(Quote $quote): Response
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->findOrFail(app('current_tenant_id'));

        return Inertia::render('Quotes/Edit', [
            'quote' => QuoteDetailData::fromModel($quote),
            'clients' => ClientOptionData::collect(
                Client::query()->select(['id', 'name'])->orderBy('name')->get(),
                DataCollection::class,
            ),
            'objects' => ObjectOptionData::collect(
                CleaningObject::query()->select(['id', 'name', 'client_id'])->where('is_active', true)->orderBy('name')->get(),
                DataCollection::class,
            ),
            'currencyOptions' => CurrencyEnum::options(),
            'kindOptions' => QuoteKindEnum::options(),
            'isVatPayer' => $tenant->is_vat_payer,
            'vatRate' => $tenant->vat_rate,
            'vatRateOptions' => [
                ['value' => 23, 'label' => '23%'],
                ['value' => 19, 'label' => '19%'],
                ['value' => 5, 'label' => '5%'],
                ['value' => 0, 'label' => '0%'],
            ],
        ]);
    }

    #[Authorize('update', 'quote')]
    public function update(QuoteUpsertData $data, Quote $quote): RedirectResponse
    {
        $this->quotes->update($quote, $data);

        return to_route('quotes.show', $quote)
            ->with('flash.success', __('app.quotes.updated'));
    }

    #[Authorize('delete', 'quote')]
    public function destroy(Quote $quote): RedirectResponse
    {
        $this->quotes->delete($quote);

        return to_route('quotes.index')
            ->with('flash.success', __('app.quotes.deleted'));
    }

    #[Authorize('send', 'quote')]
    public function send(Quote $quote): RedirectResponse
    {
        $this->quotes->send($quote);

        return to_route('quotes.show', $quote)
            ->with('flash.success', __('app.quotes.sent'));
    }

    #[Authorize('accept', 'quote')]
    public function accept(Quote $quote): RedirectResponse
    {
        $this->quotes->accept($quote);

        return to_route('quotes.show', $quote)
            ->with('flash.success', __('app.quotes.accepted'));
    }

    #[Authorize('reject', 'quote')]
    public function reject(Quote $quote): RedirectResponse
    {
        $this->quotes->reject($quote);

        return to_route('quotes.show', $quote)
            ->with('flash.success', __('app.quotes.rejected'));
    }

    #[Authorize('attachClient', 'quote')]
    public function attachClient(QuoteAttachClientData $data, Quote $quote): RedirectResponse
    {
        $this->quotes->attachToClient($quote, $data->client_id, $data->cleaning_object_id);

        return to_route('quotes.show', $quote)
            ->with('flash.success', __('app.quotes.client_attached'));
    }

    #[Authorize('update', 'quote')]
    public function uploadDocument(QuoteDocumentUploadData $data, Quote $quote): RedirectResponse
    {
        $this->quotes->attachDocument($quote, $data->document);

        return to_route('quotes.show', $quote)
            ->with('flash.success', __('app.quotes.document_uploaded'));
    }

    #[Authorize('duplicate', Quote::class)]
    public function duplicate(Quote $quote): RedirectResponse
    {
        $newQuote = $this->quotes->duplicate($quote);

        return to_route('quotes.edit', $newQuote)
            ->with('flash.success', __('app.quotes.duplicated'));
    }

    #[Authorize('convertToInvoice', 'quote')]
    public function convertToInvoice(Quote $quote): RedirectResponse
    {
        $invoice = $this->quotes->convertToInvoice($quote);

        return to_route('invoices.show', $invoice)
            ->with('flash.success', __('app.quotes.converted_to_invoice'));
    }

    #[Authorize('convertToContract', 'quote')]
    public function convertToContract(Quote $quote): RedirectResponse
    {
        $contract = $this->quotes->convertToContract($quote);

        return to_route('contracts.show', $contract)
            ->with('flash.success', __('app.quotes.converted_to_contract'));
    }

    #[Authorize('downloadPdf', 'quote')]
    public function pdf(Quote $quote, RendersQuotePdf $pdfService): HttpResponse
    {
        if ($quote->isDocument()) {
            $media = $quote->getFirstMedia('document');
            abort_if($media === null, 404, __('app.quotes.document_missing'));

            return $media->toResponse(request());
        }

        $bytes = $pdfService->render($quote);
        $filename = ($quote->number ?? 'quote') . '.pdf';

        return response()->streamDownload(
            fn () => print ($bytes),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
