<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\CleaningObject;
use App\Models\User;
use App\Services\ObjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_objects')]
#[Description('Pages through the cleaned objects (sites) of one company that the user may see. Contact data appears only for users allowed to view contacts.')]
#[IsReadOnly]
final class ListObjectsTool extends ListTool
{
    protected function filterKeys(): array
    {
        return ['search', 'type', 'client_id', 'is_active', 'city'];
    }

    public function execute(User $user, ObjectService $objects): Response
    {
        Gate::forUser($user)->authorize('viewAny', CleaningObject::class);

        return $this->json($this->pageOf($objects->paginate(request(), $user)));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'search' => $schema->string()->description('Object name, street or city contains.'),
            'type' => $schema->string()->description('Object type.'),
            'client_id' => $schema->string()->format('uuid')->description('Only objects of this client.'),
            'is_active' => $schema->string()->enum(['1', '0'])->description('1 for active, 0 for deactivated objects.'),
            'city' => $schema->string()->description('City equals.'),
            ...$this->pagingProperties($schema),
        ];
    }
}
