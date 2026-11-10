<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Invoices\PaymentImportData;
use App\Data\Invoices\PaymentImportInvoiceSearchData;
use App\Data\Invoices\PaymentImportLimitsData;
use App\Data\Invoices\PaymentImportResultData;
use App\Data\Invoices\PaymentImportUploadData;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoicePaymentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class InvoicePaymentImportController extends Controller
{
    public function __construct(private readonly InvoicePaymentImportService $imports) {}

    #[Authorize('importPayments', Invoice::class)]
    public function page(Request $request): Response
    {
        $flashed = $request->session()->get('result');

        return Inertia::render('Invoices/PaymentImport', [
            'limits' => PaymentImportLimitsData::fromConfig(),
            'result' => is_array($flashed) ? PaymentImportResultData::from($flashed) : null,
        ]);
    }

    #[Authorize('importPayments', Invoice::class)]
    public function preview(PaymentImportUploadData $data, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->imports->preview($data->files, $actor));
    }

    #[Authorize('importPayments', Invoice::class)]
    public function store(PaymentImportData $data, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return to_route('invoices.payment-import')
            ->with('result', $this->imports->import($data, $actor)->toArray())
            ->with('success', __('app.payment_import_done'));
    }

    #[Authorize('importPayments', Invoice::class)]
    public function invoices(PaymentImportInvoiceSearchData $data): JsonResponse
    {
        return response()->json($this->imports->searchInvoices($data));
    }
}
