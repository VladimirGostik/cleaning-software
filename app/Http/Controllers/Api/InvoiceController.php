<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\GeneratesPaymentQr;
use App\Contracts\RendersInvoicePdf;
use App\Data\Invoices\InvoiceDetailData;
use App\Data\Invoices\InvoiceListItemData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\InvoiceController as WebInvoiceController;
use App\Models\Invoice;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\Efa\EInvoiceService;
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

#[Group('Invoices', 'Read-only invoices of the active company')]
#[Authenticated]
final class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

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

    #[Authorize('view', 'invoice')]
    #[Endpoint('Show invoice', 'Invoice with items, payments and VAT breakdown.')]
    #[ResponseFromSpatieData(InvoiceDetailData::class, User::class)]
    public function show(Invoice $invoice, GeneratesPaymentQr $qr, EInvoiceService $efa): JsonResponse
    {
        $invoice->load(['items', 'client', 'tenant', 'quote:id,number']);

        return response()->json(InvoiceDetailData::fromModel($invoice, $qr->dataUri($invoice), $efa->status($invoice)));
    }

    #[Authorize('downloadPdf', 'invoice')]
    #[Endpoint('Invoice PDF', 'Streams the invoice PDF inline.')]
    public function pdf(Invoice $invoice, RendersInvoicePdf $pdfService, WebInvoiceController $web): Response
    {
        return PdfResponse::inline($web->pdf($invoice, $pdfService));
    }
}
