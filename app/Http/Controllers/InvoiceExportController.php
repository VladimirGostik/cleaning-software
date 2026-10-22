<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Invoices\PohodaExportData;
use App\Data\Invoices\PohodaExportPreviewData;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Export\PohodaXmlBuilder;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class InvoiceExportController extends Controller
{
    public function __construct(private readonly PohodaXmlBuilder $pohoda) {}

    #[Authorize('export', Invoice::class)]
    public function pohoda(PohodaExportData $data): Response
    {
        $xml = $this->pohoda->export($this->tenant(), $data);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset='.config()->string('pohoda.encoding'),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'pohoda-'.$data->from.'-'.$data->to.'.xml'),
        ]);
    }

    #[Authorize('export', Invoice::class)]
    public function pohodaPreview(PohodaExportData $data): PohodaExportPreviewData
    {
        return $this->pohoda->preview($this->tenant(), $data);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(current_tenant_id());
    }
}
