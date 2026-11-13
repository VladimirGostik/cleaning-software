<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Contacts\ContactData;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/** @mixin TenantTool */
trait DescribesContactOwner
{
    /** @return array<string, mixed> */
    protected function ownerRules(): array
    {
        return [
            'object_id' => ['required_without:client_id', 'prohibits:client_id', 'uuid'],
            'client_id' => ['required_without:object_id', 'prohibits:object_id', 'uuid'],
        ];
    }

    protected function owner(Request $request): Client|CleaningObject
    {
        $objectId = $request->get('object_id');

        return is_string($objectId) && $objectId !== ''
            ? CleaningObject::query()->findOrFail($objectId)
            : Client::query()->findOrFail($this->id($request, 'client_id'));
    }

    /** @return array<string, Type> */
    protected function ownerProperties(JsonSchema $schema): array
    {
        return [
            'object_id' => $schema->string()->format('uuid')->description('Object id from search, list_objects or get_client. Give object_id or client_id, not both.'),
            'client_id' => $schema->string()->format('uuid')->description('Client id from search, list_clients or get_client. Give object_id or client_id, not both.'),
        ];
    }

    /** @return array<string, Type> */
    protected function contactProperties(JsonSchema $schema): array
    {
        return [
            'position' => $schema->string()->max(255)->nullable()->description('Role of the person, e.g. caretaker or manager. Never an address or other free text.'),
            'email' => $schema->string()->max(255)->nullable()->description('E-mail address.'),
            'phone' => $schema->string()->max(64)->nullable()->description('Phone number as the user gave it.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function auditedArguments(array $arguments): array
    {
        $kept = array_intersect_key($arguments, array_flip(['company_id', 'object_id', 'client_id', 'contact_id', 'is_primary']));
        $fields = array_values(array_filter(['email', 'name', 'phone', 'position'], static fn (string $key): bool => array_key_exists($key, $arguments)));

        return $fields === [] ? $kept : [...$kept, 'fields' => $fields];
    }

    protected function contactResponse(Client|CleaningObject $owner, ?Contact $contact = null): Response
    {
        $contacts = $owner->contacts()->getQuery()->reorder()->orderByDesc('is_primary')->orderBy('name')->get();

        return $this->json([
            'owner' => ['type' => $owner instanceof Client ? 'client' : 'object', 'id' => $owner->id, 'name' => $owner->name],
            ...($contact === null ? [] : ['contact' => ContactData::from($contact)->toArray()]),
            'contacts' => ContactData::collect($contacts)->toArray(),
        ]);
    }
}
