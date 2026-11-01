<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Contracts\RendersInvoicePdf;
use App\Models\Invoice;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Support\UriTemplate;

#[Name('invoice-pdf')]
#[Description('PDF of one invoice: invoice-pdf://{company}/{invoice}.')]
final class InvoicePdfResource extends PdfResource
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('invoice-pdf://{company}/{invoice}');
    }

    protected function resourceName(): string
    {
        return 'invoice_pdf';
    }

    protected function idKey(): string
    {
        return 'invoice';
    }

    protected function renderer(User $user): Closure
    {
        return function (string $id) use ($user): string {
            $invoice = Invoice::query()->findOrFail($id);

            Gate::forUser($user)->authorize('downloadPdf', $invoice);

            return app(RendersInvoicePdf::class)->render($invoice);
        };
    }
}
