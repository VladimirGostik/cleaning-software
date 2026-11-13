<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_contacts')]
#[Description('Contact persons (name, position, phone, e-mail) of one object or one client. Call it before create_contact to spot an existing match, and to get contact_id for update_contact or prepare_action delete_contact.')]
#[IsReadOnly]
final class ListContactsTool extends TenantTool
{
    use DescribesContactOwner;

    protected function rules(): array
    {
        return $this->ownerRules();
    }

    public function execute(Request $request, User $user): Response
    {
        $owner = $this->owner($request);

        Gate::forUser($user)->authorize('viewOn', [Contact::class, $owner]);

        return $this->contactResponse($owner);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [...$this->companyProperty($schema), ...$this->ownerProperties($schema)];
    }
}
