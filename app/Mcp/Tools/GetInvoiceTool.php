<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Invoices\InvoiceItemData;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_invoice')]
#[Description('One invoice with customer, dates, totals and line items.')]
#[IsReadOnly]
final class GetInvoiceTool extends TenantTool
{
    protected function rules(): array
    {
        return ['invoice_id' => ['required', 'uuid']];
    }

    public function execute(Request $request, User $user): Response
    {
        $invoice = Invoice::query()->with(['items', 'client:id,name'])->findOrFail($this->id($request, 'invoice_id'));

        Gate::forUser($user)->authorize('view', $invoice);

        return $this->json([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'type' => $invoice->type->value,
            'client_id' => $invoice->client_id,
            'issue_date' => $invoice->issue_date->toDateString(),
            'delivery_date' => $invoice->delivery_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'currency' => $invoice->currency->value,
            'subtotal' => $invoice->subtotal,
            'vat_amount' => $invoice->vat_amount,
            'total' => $invoice->total,
            'paid_amount' => $invoice->paid_amount,
            'customer' => [
                'name' => $invoice->customer_name,
                'ico' => $invoice->customer_ico,
                'email' => $invoice->customer_email,
                'street' => $invoice->customer_street,
                'city' => $invoice->customer_city,
                'postal_code' => $invoice->customer_postal_code,
            ],
            'note' => $invoice->note,
            'items' => $invoice->items->map(fn ($item): array => InvoiceItemData::fromModel($item)->toArray())->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'invoice_id' => $schema->string()->format('uuid')->description('Invoice id from list_invoices.')->required(),
        ];
    }
}
