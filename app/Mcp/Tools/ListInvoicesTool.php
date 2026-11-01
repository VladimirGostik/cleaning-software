<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_invoices')]
#[Description('Pages through the invoices of one company. Filter by search (number or customer), status or type.')]
#[IsReadOnly]
final class ListInvoicesTool extends ListTool
{
    protected function filterKeys(): array
    {
        return ['search', 'status', 'type', 'client_id'];
    }

    public function execute(User $user, InvoiceService $invoices): Response
    {
        Gate::forUser($user)->authorize('viewAny', Invoice::class);

        return $this->json($this->pageOf($invoices->paginate(request())));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'search' => $schema->string()->description('Invoice number or customer name contains.'),
            'status' => $schema->string()->enum(['draft', 'issued', 'overdue', 'paid', 'cancelled'])->description('Invoice status.'),
            'type' => $schema->string()->enum(['monthly', 'one_off', 'special'])->description('Invoice type.'),
            'client_id' => $schema->string()->format('uuid')->description('Only invoices of this client.'),
            ...$this->pagingProperties($schema),
        ];
    }
}
