<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Client;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_clients')]
#[Description('Pages through the clients of one company. Filter by search (name or company number) or city.')]
#[IsReadOnly]
final class ListClientsTool extends ListTool
{
    protected function filterKeys(): array
    {
        return ['search', 'type', 'city'];
    }

    public function execute(User $user, ClientService $clients): Response
    {
        Gate::forUser($user)->authorize('viewAny', Client::class);

        return $this->json($this->pageOf($clients->paginate(request())));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'search' => $schema->string()->description('Client name or company number contains.'),
            'type' => $schema->string()->description('Client type.'),
            'city' => $schema->string()->description('City equals.'),
            ...$this->pagingProperties($schema),
        ];
    }
}
