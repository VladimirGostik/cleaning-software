<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Clients\ClientDetailData;
use App\Data\Objects\ObjectListItemData;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_client')]
#[Description('One client with contacts and the objects (sites) of that client the user may see. Object contact data only for users allowed to view contacts.')]
#[IsReadOnly]
final class GetClientTool extends TenantTool
{
    protected function rules(): array
    {
        return ['client_id' => ['required', 'uuid']];
    }

    public function execute(Request $request, User $user): Response
    {
        $client = Client::query()->with('contacts')->findOrFail($this->id($request, 'client_id'));

        $gate = Gate::forUser($user);
        $gate->authorize('view', $client);

        $includeContacts = $gate->allows('viewContacts', CleaningObject::class);

        $objects = $client->objects()->visibleTo($user)->with('client:id,name')->orderBy('name');

        if ($includeContacts) {
            $objects->withCount('contacts')->with('primaryContact');
        }

        return $this->json([
            'client' => ClientDetailData::fromModel($client),
            'objects' => $objects->get()->map(fn (CleaningObject $object): ObjectListItemData => ObjectListItemData::fromModel($object, $includeContacts))->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'client_id' => $schema->string()->format('uuid')->description('Client id from list_clients.')->required(),
        ];
    }
}
