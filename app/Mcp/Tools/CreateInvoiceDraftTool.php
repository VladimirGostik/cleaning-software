<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Invoices\InvoiceUpsertData;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_invoice_draft')]
#[Description('Creates an invoice as a draft; no number is consumed. Issuing and sending are separate two-step actions (prepare_action).')]
#[IsDestructive(false)]
final class CreateInvoiceDraftTool extends TenantTool
{
    use DescribesDocumentItems;

    public function execute(Request $request, User $user, InvoiceService $invoices): Response
    {
        Gate::forUser($user)->authorize('create', Invoice::class);

        $invoice = $invoices->create(InvoiceUpsertData::validateAndCreate($this->invoiceFields($request)));

        return $this->json(['id' => $invoice->id, 'status' => $invoice->status->value, 'total' => $invoice->total]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            ...$this->invoiceProperties($schema),
        ];
    }
}
