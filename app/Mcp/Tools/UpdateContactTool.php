<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Contacts\ContactPatchData;
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

#[Name('update_contact')]
#[Description('Changes a contact person of one object or one client; contact_id comes from list_contacts. Omitted fields stay as they are, null clears position, e-mail or phone. is_primary true makes this the primary contact (it cannot be set to false: make another contact primary instead). Removing a contact is prepare_action delete_contact. A contact has only name, position, phone and e-mail: never put an address or other text into position.')]
#[IsDestructive]
final class UpdateContactTool extends TenantTool
{
    use DescribesContactOwner;

    protected function rules(): array
    {
        return [...$this->ownerRules(), 'contact_id' => ['required', 'uuid']];
    }

    public function execute(Request $request, User $user, ContactService $contacts): Response
    {
        $owner = $this->owner($request);
        $contact = $owner->contacts()->findOrFail($this->id($request, 'contact_id'));

        Gate::forUser($user)->authorize('update', $contact);

        $data = ContactPatchData::validateAndCreate(Arr::only($request->all(), ['name', 'position', 'email', 'phone', 'is_primary']));

        return $this->contactResponse($owner, $contacts->update($contact, $data));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            ...$this->ownerProperties($schema),
            'contact_id' => $schema->string()->format('uuid')->description('Contact id from list_contacts.')->required(),
            'name' => $schema->string()->max(255)->description('New full name.'),
            ...$this->contactProperties($schema),
            'is_primary' => $schema->boolean()->description('Only true: make this the primary contact.'),
        ];
    }
}
