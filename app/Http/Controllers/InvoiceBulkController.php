<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Invoices\BulkActionResultData;
use App\Data\Invoices\InvoiceBulkActionData;
use App\Data\Invoices\InvoiceBulkPayData;
use App\Data\Invoices\InvoiceBulkPdfData;
use App\Exceptions\PdfMergeFailedException;
use App\Models\Invoice;
use App\Services\InvoiceBulkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class InvoiceBulkController extends Controller
{
    public function __construct(private readonly InvoiceBulkService $bulk) {}

    #[Authorize('bulkPay', Invoice::class)]
    public function pay(InvoiceBulkPayData $data, Request $request): RedirectResponse
    {
        return $this->respond($this->bulk->pay($data->ids, $data->paid_on, $request->user()), $data);
    }

    #[Authorize('bulkSend', Invoice::class)]
    public function send(InvoiceBulkActionData $data): RedirectResponse
    {
        return $this->respond($this->bulk->send($data->ids), $data);
    }

    #[Authorize('bulkIssue', Invoice::class)]
    public function issue(InvoiceBulkActionData $data, Request $request): RedirectResponse
    {
        return $this->respond($this->bulk->issue($data->ids, $request->user()), $data);
    }

    #[Authorize('bulkPdf', Invoice::class)]
    public function pdf(InvoiceBulkPdfData $data): Response|RedirectResponse
    {
        try {
            $content = $this->bulk->mergedPdf($data->ids);
        } catch (PdfMergeFailedException $e) {
            Log::error('invoice.bulk_pdf.merge_failed', ['exit_code' => $e->exitCode, 'stderr' => $e->stderr]);

            return back()->with('error', __('app.invoice_bulk_pdf_failed'));
        }

        $filename = 'faktury-'.now()->toDateString().'.pdf';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        ]);
    }

    private function respond(BulkActionResultData $result, InvoiceBulkActionData|InvoiceBulkPayData $data): RedirectResponse
    {
        return back()
            ->with('bulk_result', $result->toArray())
            ->with('success', __('app.invoice_bulk_done', ['processed' => $result->processed, 'total' => count($data->ids)]));
    }
}
