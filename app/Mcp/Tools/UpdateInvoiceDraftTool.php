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

#[Name('update_invoice_draft')]
#[Description('Changes a draft invoice. Omitted fields stay as they are; items, when given, replace all lines. Only drafts are editable.')]
#[IsDestructive]
final class UpdateInvoiceDraftTool extends TenantTool
{
    use DescribesDocumentItems;

    protected function rules(): array
    {
        return ['invoice_id' => ['required', 'uuid']];
    }

    public function execute(Request $request, User $user, InvoiceService $invoices): Response
    {
        $invoice = Invoice::query()->findOrFail($this->id($request, 'invoice_id'));

        Gate::forUser($user)->authorize('update', $invoice);

        $updated = $invoices->update($invoice, InvoiceUpsertData::validateAndCreate($this->invoiceFields($request, $invoice)));

        return $this->json(['id' => $updated->id, 'status' => $updated->status->value, 'total' => $updated->total]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'invoice_id' => $schema->string()->format('uuid')->description('Draft invoice id.')->required(),
            ...$this->invoiceProperties($schema),
        ];
    }
}
