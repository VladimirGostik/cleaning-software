<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class QuoteBuyerPreviewTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Client $client;

    private CleaningObject $object;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Spravca s.r.o.']);
        $this->object = CleaningObject::factory()
            ->withBillingIdentity(['billing_name' => 'Dom Lipova'])
            ->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
        $this->object->forceFill(['billing_email' => 'faktury@example.com'])->save();
    }

    /** @return array<string, string> */
    private function params(): array
    {
        return ['client_id' => $this->client->id, 'cleaning_object_id' => $this->object->id];
    }

    public function test_preview_returns_the_house_buyer_with_the_billing_email(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);

        $this->getJson(route('quotes.buyer-preview', $this->params()))
            ->assertOk()
            ->assertJsonPath('buyer.source', 'object_billing')
            ->assertJsonPath('buyer.name', 'Dom Lipova')
            ->assertJsonPath('buyer.represented_by', null)
            ->assertJsonPath('buyer.email', 'faktury@example.com')
            ->assertJsonStructure(['buyer', 'email_options']);
    }

    public function test_preview_without_object_uses_the_client_only(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);

        $this->getJson(route('quotes.buyer-preview', ['client_id' => $this->client->id]))
            ->assertOk()
            ->assertJsonPath('buyer.source', 'client')
            ->assertJsonPath('buyer.name', 'Spravca s.r.o.')
            ->assertJsonPath('buyer.email', null);
    }

    public function test_without_create_or_edit_quotes_is_forbidden(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $role = Role::inTenant($this->tenant->id)->where('name', 'Admin')->firstOrFail();
        $role->revokePermissionTo(PermissionEnum::CreateQuotes->value);
        $role->revokePermissionTo(PermissionEnum::EditQuotes->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson(route('quotes.buyer-preview', $this->params()))->assertForbidden();
    }

    public function test_foreign_object_is_rejected(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $other = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->getJson(route('quotes.buyer-preview', ['client_id' => $other->id, 'cleaning_object_id' => $this->object->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cleaning_object_id');
    }
}
