<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\GeneratesPaymentQr;
use App\Contracts\RendersInvoicePdf;
use App\Data\Invoices\InvoiceDetailData;
use App\Data\Invoices\InvoiceIssueData;
use App\Data\Invoices\InvoiceListItemData;
use App\Data\Invoices\InvoiceSendData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InvoiceController as WebInvoiceController;
use App\Models\Invoice;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\Efa\EInvoiceService;
use App\Services\InvoiceRecipientService;
use App\Services\InvoiceService;
use App\Utils\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Symfony\Component\HttpFoundation\Response;

#[Group('Invoices', 'Invoices of the active company: list, detail, issue and send')]
#[Authenticated]
final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly GeneratesPaymentQr $qr,
        private readonly EInvoiceService $efa,
    ) {}

    #[Authorize('viewAny', Invoice::class)]
    #[Endpoint('List invoices', 'Paginated invoices of the active company.')]
    #[QueryParam('filter[search]', 'string', 'Number or customer name contains.', required: false)]
    #[QueryParam('filter[status]', 'string', 'Invoice status.', required: false, example: 'issued')]
    #[QueryParam('sort', 'string', 'number, status, issue_date, due_date, total; prefix - for descending.', required: false)]
    #[ResponseFromSpatieData(InvoiceListItemData::class, User::class, paginated: true)]
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->invoices->paginate(PerPage::clamp($request)));
    }

    #[Authorize('viewAny', Invoice::class)]
    #[Endpoint('Invoice stats', 'Overview cards of the active company.')]
    public function stats(): JsonResponse
    {
        return response()->json($this->invoices->stats());
    }

    #[Authorize('view', 'invoice')]
    #[Endpoint('Show invoice', 'Invoice with items, payments and VAT breakdown.')]
    #[ResponseFromSpatieData(InvoiceDetailData::class, User::class)]
    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json($this->detail($invoice));
    }

    #[Authorize('issue', 'invoice')]
    #[Endpoint('Issue invoice', 'Issues a draft invoice. An empty body takes the next number in the series.')]
    #[ResponseFromSpatieData(InvoiceDetailData::class, User::class)]
    public function issue(InvoiceIssueData $data, Invoice $invoice, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->detail($this->invoices->issue($invoice, $data, $actor)));
    }

    #[Authorize('send', 'invoice')]
    #[Endpoint('Send draft', 'Resolved cover e-mail (subject and text) and recipient options for an issued or overdue invoice.')]
    public function sendDraft(Invoice $invoice, Request $request, InvoiceRecipientService $recipients): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($recipients->sendDraft($invoice, $actor));
    }

    #[Authorize('send', 'invoice')]
    #[Endpoint('Send invoice', 'Queues the invoice e-mail with the PDF. Optional subject and body override the firm template for this send.')]
    #[ResponseFromSpatieData(InvoiceDetailData::class, User::class, status: 202)]
    public function send(InvoiceSendData $data, Invoice $invoice, Request $request, InvoiceRecipientService $recipients): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $recipients->send($invoice, $data, $actor);

        return response()->json($this->detail($invoice->refresh()), Response::HTTP_ACCEPTED);
    }

    private function detail(Invoice $invoice): InvoiceDetailData
    {
        $invoice->load(['items', 'client', 'tenant', 'quote:id,number']);

        return InvoiceDetailData::fromModel($invoice, $this->qr->dataUri($invoice), $this->efa->status($invoice));
    }

    #[Authorize('downloadPdf', 'invoice')]
    #[Endpoint('Invoice PDF', 'Streams the invoice PDF inline.')]
    public function pdf(Invoice $invoice, RendersInvoicePdf $pdfService, WebInvoiceController $web): Response
    {
        return PdfResponse::inline($web->pdf($invoice, $pdfService));
    }
}
