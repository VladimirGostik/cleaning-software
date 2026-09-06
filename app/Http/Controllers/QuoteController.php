<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\RendersQuotePdf;
use App\Data\Quotes\QuoteAttachClientData;
use App\Data\Quotes\QuoteDetailData;
use App\Data\Quotes\QuoteFormContextData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\PermissionEnum;
use App\Enums\QuoteKindEnum;
use App\Http\Controllers\Concerns\ProvidesSubjectOptions;
use App\Models\Quote;
use App\Models\Tenant;
use App\Navigation\NavItem;
use App\Services\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

final class QuoteController extends Controller
{
    use ProvidesSubjectOptions;

    public function __construct(private readonly QuoteService $quotes) {}

    #[Authorize('viewAny', Quote::class)]
    #[NavItem(label: 'app.quotes', route: 'quotes.index', icon: 'DocumentDuplicateIcon', permission: PermissionEnum::ViewQuotes->value, order: 35)]
    public function index(Request $request): InertiaResponse
    {
        return Inertia::render('Quotes/Index', [
            'quotes' => $this->quotes->paginate($request),
            'filters' => $request->query(),
            'filterOptions' => [
                'clients' => $this->clientOptions(),
            ],
        ]);
    }

    #[Authorize('create', Quote::class)]
    public function create(): InertiaResponse
    {
        $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

        return Inertia::render('Quotes/Create', [
            'context' => QuoteFormContextData::fromTenant($tenant, $this->clientOptions(), $this->objectOptions()),
        ]);
    }

    #[Authorize('create', Quote::class)]
    public function store(QuoteUpsertData $data, Request $request): RedirectResponse
    {
        $quote = $this->quotes->create($data, $request->user(), $request->session()->getId());

        return to_route('quotes.show', $quote)->with('success', __('app.quote_created'));
    }

    #[Authorize('view', 'quote')]
    public function show(Quote $quote): InertiaResponse
    {
        $isClientless = $quote->client_id === null;

        return Inertia::render('Quotes/Show', [
            'quote' => QuoteDetailData::fromModel($quote),
            'clients' => $isClientless ? $this->clientOptions() : null,
            'objects' => $isClientless ? $this->objectOptions() : null,
        ]);
    }

    #[Authorize('update', 'quote')]
    public function edit(Quote $quote): InertiaResponse
    {
        $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

        return Inertia::render('Quotes/Edit', [
            'quote' => QuoteDetailData::fromModel($quote),
            'context' => QuoteFormContextData::fromTenant(
                $tenant,
                $this->clientOptions(),
                $this->objectOptions($quote->cleaning_object_id),
            ),
        ]);
    }

    #[Authorize('update', 'quote')]
    public function update(QuoteUpsertData $data, Quote $quote, Request $request): RedirectResponse
    {
        $this->quotes->update($quote, $data, $request->user(), $request->session()->getId());

        return to_route('quotes.show', $quote)->with('success', __('app.quote_updated'));
    }

    #[Authorize('delete', 'quote')]
    public function destroy(Quote $quote): RedirectResponse
    {
        $this->quotes->delete($quote);

        return to_route('quotes.index')->with('success', __('app.quote_deleted'));
    }

    #[Authorize('send', 'quote')]
    public function send(Quote $quote): RedirectResponse
    {
        $this->quotes->send($quote);

        return to_route('quotes.show', $quote)->with('success', __('app.quote_sent'));
    }

    #[Authorize('accept', 'quote')]
    public function accept(Quote $quote): RedirectResponse
    {
        $this->quotes->accept($quote);

        return to_route('quotes.show', $quote)->with('success', __('app.quote_accepted'));
    }

    #[Authorize('reject', 'quote')]
    public function reject(Quote $quote): RedirectResponse
    {
        $this->quotes->reject($quote);

        return to_route('quotes.show', $quote)->with('success', __('app.quote_rejected'));
    }

    #[Authorize('duplicate', 'quote')]
    public function duplicate(Quote $quote): RedirectResponse
    {
        $newQuote = $this->quotes->duplicate($quote);

        return to_route('quotes.edit', $newQuote)->with('success', __('app.quote_duplicated'));
    }

    #[Authorize('attachClient', 'quote')]
    public function attachClient(QuoteAttachClientData $data, Quote $quote): RedirectResponse
    {
        $this->quotes->attachClient($quote, $data->client_id, $data->cleaning_object_id);

        return to_route('quotes.show', $quote)->with('success', __('app.quote_client_attached'));
    }

    #[Authorize('convertToInvoice', 'quote')]
    public function convertToInvoice(Quote $quote): RedirectResponse
    {
        $invoice = $this->quotes->convertToInvoice($quote);

        return to_route('invoices.show', $invoice)->with('success', __('app.quote_converted_to_invoice'));
    }

    #[Authorize('downloadPdf', 'quote')]
    public function pdf(Quote $quote, RendersQuotePdf $pdfService): Response
    {
        if ($quote->kind === QuoteKindEnum::Document) {
            $media = $quote->getFirstMedia('document');

            if ($media === null) {
                abort(404);
            }

            return response()->streamDownload(
                static function () use ($media): void {
                    /** @var resource $stream */
                    $stream = $media->stream();
                    fpassthru($stream);
                },
                $media->file_name,
                ['Content-Type' => (string) $media->mime_type],
            );
        }

        $pdfContent = $pdfService->render($quote);

        $filename = $quote->pdfFilenameBase().'.pdf';

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
