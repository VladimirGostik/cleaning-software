<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Contacts\ContactData;
use App\Models\Contact;
use App\Models\User;
use App\Services\ContactService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_contact')]
#[Description('Adds a contact person to one object or one client. First find the object or client (search or list_objects by name, street or city; get_client for the objects of a client); when several match, or it is unclear whether the client or the object is meant, ask the user. Then call list_contacts: when a contact with the same name, phone or e-mail already exists, tell the user what is stored and ask whether to update it, add another one or stop; never overwrite on your own. A contact has only name, position, phone and e-mail: never put an address or other text into position. is_primary only when the user asks for it (the first contact of an owner becomes primary anyway).')]
#[IsDestructive(false)]
final class CreateContactTool extends TenantTool
{
    use DescribesContactOwner;

    protected function rules(): array
    {
        return [...$this->ownerRules(), 'name' => ['required']];
    }

    public function execute(Request $request, User $user, ContactService $contacts): Response
    {
        $owner = $this->owner($request);

        Gate::forUser($user)->authorize('manageOn', [Contact::class, $owner]);

        $data = ContactData::validateAndCreate([
            ...Arr::only($request->all(), ['name', 'position', 'email', 'phone', 'is_primary']),
            'id' => null,
        ]);

        return $this->contactResponse($owner, $contacts->create($owner, $data));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            ...$this->ownerProperties($schema),
            'name' => $schema->string()->max(255)->description('Full name of the person.')->required(),
            ...$this->contactProperties($schema),
            'is_primary' => $schema->boolean()->description('Make this the primary contact; the current primary is demoted.'),
        ];
    }
}
