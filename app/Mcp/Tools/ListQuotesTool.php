<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_quotes')]
#[Description('Pages through the quotes of one company. Filter by search (number, subject or customer) or status.')]
#[IsReadOnly]
final class ListQuotesTool extends ListTool
{
    protected function filterKeys(): array
    {
        return ['search', 'status', 'kind', 'client_id'];
    }

    public function execute(User $user, QuoteService $quotes): Response
    {
        Gate::forUser($user)->authorize('viewAny', Quote::class);

        return $this->json($this->pageOf($quotes->paginate(request())));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'search' => $schema->string()->description('Quote number, subject or customer name contains.'),
            'status' => $schema->string()->enum(['draft', 'sent', 'accepted', 'rejected', 'expired'])->description('Quote status.'),
            'kind' => $schema->string()->enum(['itemized', 'document'])->description('Quote kind.'),
            'client_id' => $schema->string()->format('uuid')->description('Only quotes of this client.'),
            ...$this->pagingProperties($schema),
        ];
    }
}
