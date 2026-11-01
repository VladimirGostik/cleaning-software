<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\RendersQuotePdf;
use App\Data\Quotes\QuoteDetailData;
use App\Data\Quotes\QuoteListItemData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\QuoteController as WebQuoteController;
use App\Models\Quote;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\QuoteService;
use App\Utils\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Symfony\Component\HttpFoundation\Response;

#[Group('Quotes', 'Read-only quotes of the active company')]
#[Authenticated]
final class QuoteController extends Controller
{
    public function __construct(private readonly QuoteService $quotes) {}

    #[Authorize('viewAny', Quote::class)]
    #[Endpoint('List quotes', 'Paginated quotes of the active company.')]
    #[QueryParam('filter[search]', 'string', 'Number, subject or customer contains.', required: false)]
    #[QueryParam('filter[status]', 'string', 'Quote status.', required: false, example: 'sent')]
    #[QueryParam('sort', 'string', 'number, status, kind, issue_date, valid_until, total, created_at; prefix - for descending.', required: false)]
    #[ResponseFromSpatieData(QuoteListItemData::class, User::class, paginated: true)]
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->quotes->paginate(PerPage::clamp($request)));
    }

    #[Authorize('view', 'quote')]
    #[Endpoint('Show quote', 'Quote with items, VAT breakdown and linked invoices/contracts.')]
    #[ResponseFromSpatieData(QuoteDetailData::class, User::class)]
    public function show(Quote $quote): JsonResponse
    {
        return response()->json(QuoteDetailData::fromModel($quote));
    }

    #[Authorize('downloadPdf', 'quote')]
    #[Endpoint('Quote PDF', 'Streams the quote PDF (or the uploaded document) inline.')]
    public function pdf(Quote $quote, RendersQuotePdf $pdfService, WebQuoteController $web): Response
    {
        return PdfResponse::inline($web->pdf($quote, $pdfService));
    }
}
