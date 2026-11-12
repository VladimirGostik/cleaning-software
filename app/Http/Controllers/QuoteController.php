<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\RendersQuotePdf;
use App\Data\DocumentTemplates\DocumentTemplateOptionData;
use App\Data\Invoices\InvoiceBuyerPreviewData;
use App\Data\Invoices\InvoiceBuyerPreviewResultData;
use App\Data\Quotes\QuoteAttachClientData;
use App\Data\Quotes\QuoteConvertToContractData;
use App\Data\Quotes\QuoteDetailData;
use App\Data\Quotes\QuoteFormContextData;
use App\Data\Quotes\QuoteTemplateOptionData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\DocumentTypeEnum;
use App\Enums\NavIconEnum;
use App\Enums\PermissionEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Http\Controllers\Concerns\ProvidesSubjectOptions;
use App\Models\DocumentTemplate;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\Documents\DocumentTemplateService;
use App\Services\Documents\QuoteTextService;
use App\Services\InvoiceRecipientService;
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

    public function __construct(
        private readonly QuoteService $quotes,
        private readonly QuoteTextService $text,
        private readonly DocumentTemplateService $templates,
    ) {}

    #[Authorize('previewBuyer', Quote::class)]
    public function buyerPreview(InvoiceBuyerPreviewData $data, Request $request, InvoiceRecipientService $recipients): InvoiceBuyerPreviewResultData
    {
        /** @var User $actor */
        $actor = $request->user();

        return $recipients->previewBuyer($data, $actor);
    }

    #[Authorize('viewAny', Quote::class)]
    #[NavItem(label: 'app.quotes', route: 'quotes.index', icon: NavIconEnum::Quotes, permission: PermissionEnum::ViewQuotes->value, order: 35)]
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
    public function create(Request $request): InertiaResponse
    {
        $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

        /** @var User $actor */
        $actor = $request->user();

        $prefill = $this->quotes->createPrefill(
            $request->string('kind')->toString() ?: null,
            $request->string('client_id')->toString() ?: null,
            $request->string('cleaning_object_id')->toString() ?: null,
            $actor,
        );

        return Inertia::render('Quotes/Create', [
            'context' => $this->formContext($tenant, $prefill->cleaning_object_id),
            'prefill' => $prefill,
        ]);
    }

    #[Authorize('create', Quote::class)]
    public function store(QuoteUpsertData $data, Request $request): RedirectResponse
    {
        $quote = $this->quotes->create($data, $request->user(), $request->session()->getId());

        return to_route('quotes.show', $quote)->with('success', __('app.quote_created'));
    }

    #[Authorize('view', 'quote')]
    public function show(Quote $quote, Request $request): InertiaResponse
    {
        $isClientless = $quote->client_id === null;

        /** @var User $actor */
        $actor = $request->user();

        $canConvertToContract = $quote->status === QuoteStatusEnum::Accepted
            && $quote->kind === QuoteKindEnum::Itemized
            && $actor->can('convertToContract', $quote);

        return Inertia::render('Quotes/Show', [
            'quote' => QuoteDetailData::fromModel($quote, $this->text),
            'clients' => $isClientless ? $this->clientOptions() : null,
            'objects' => $isClientless ? $this->objectOptions() : null,
            'contractTemplates' => $canConvertToContract ? $this->contractTemplateOptions() : [],
        ]);
    }

    #[Authorize('update', 'quote')]
    public function edit(Quote $quote): InertiaResponse
    {
        $tenant = Tenant::query()->with('interface')->findOrFail(current_tenant_id());

        return Inertia::render('Quotes/Edit', [
            'quote' => QuoteDetailData::fromModel($quote, $this->text, rawText: true),
            'context' => $this->formContext($tenant, $quote->cleaning_object_id),
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

    #[Authorize('convertToContract', 'quote')]
    public function convertToContract(QuoteConvertToContractData $data, Quote $quote, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $contract = $this->quotes->convertToContract($quote, $data, $actor);

        return to_route('contracts.edit', $contract)->with('success', __('app.quote_converted_to_contract'));
    }

    #[Authorize('downloadPdf', 'quote')]
    public function pdf(Quote $quote, RendersQuotePdf $pdfService): Response
    {
        return $this->pdfResponse($quote, $pdfService, HeaderUtils::DISPOSITION_ATTACHMENT);
    }

    #[Authorize('downloadPdf', 'quote')]
    public function preview(Quote $quote, RendersQuotePdf $pdfService): Response
    {
        return $this->pdfResponse($quote, $pdfService, HeaderUtils::DISPOSITION_INLINE);
    }

    private function pdfResponse(Quote $quote, RendersQuotePdf $pdfService, string $disposition): Response
    {
        if ($quote->kind === QuoteKindEnum::Document) {
            $media = $quote->document?->getFirstMedia('file');

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
                ['Content-Type' => (string) $media->mime_type, 'Cache-Control' => 'private, no-store'],
                $disposition,
            );
        }

        $filename = $quote->pdfFilenameBase().'.pdf';

        return response($pdfService->render($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename, Str::ascii($filename)),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array<int, DocumentTemplateOptionData> */
    private function contractTemplateOptions(): array
    {
        return array_map(
            fn (DocumentTemplate $template) => DocumentTemplateOptionData::fromModel($template),
            $this->templates->options(DocumentTypeEnum::ServiceAgreement),
        );
    }

    private function formContext(Tenant $tenant, ?string $keepObjectId = null): QuoteFormContextData
    {
        $default = $this->text->defaultTemplate($tenant->id);

        return QuoteFormContextData::fromTenant(
            $tenant,
            $this->clientOptions(),
            $this->objectOptions($keepObjectId),
            array_map(
                fn (DocumentTemplate $template) => QuoteTemplateOptionData::fromModel($template),
                $this->templates->options(DocumentTypeEnum::Quote),
            ),
            $this->text->tokens(),
            $default?->id,
            $this->text->bodyOf($default),
        );
    }
}
