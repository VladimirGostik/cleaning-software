<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Efa\EInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class EInvoiceController extends Controller
{
    public function __construct(private readonly EInvoiceService $efa) {}

    #[Authorize('downloadEfaXml', 'invoice')]
    public function xml(Invoice $invoice): Response
    {
        $xml = $this->efa->xml($invoice);
        $filename = $invoice->pdfFilenameBase().'.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                Str::ascii($filename),
            ),
        ]);
    }

    #[Authorize('submitEfa', 'invoice')]
    public function submit(Invoice $invoice): RedirectResponse
    {
        $invoice = $this->efa->submit($invoice);

        return to_route('invoices.show', $invoice)->with('success', __('app.efa_submitted'));
    }
}
