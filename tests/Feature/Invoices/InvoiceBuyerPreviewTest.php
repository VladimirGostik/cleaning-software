<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class InvoiceBuyerPreviewTest extends TestCase
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
        $this->object->forceFill(['billing_email' => 'Faktury@Example.com'])->save();
    }

    /** @param  list<PermissionEnum>  $permissions */
    private function revoke(array $permissions): void
    {
        $role = Role::inTenant($this->tenant->id)->where('name', 'Admin')->firstOrFail();

        foreach ($permissions as $permission) {
            $role->revokePermissionTo($permission->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function contact(Client|CleaningObject $owner, string $email, bool $primary = false): void
    {
        Contact::factory()->for($owner, 'contactable')->create([
            'tenant_id' => $this->tenant->id,
            'email' => $email,
            'is_primary' => $primary,
            'name' => $email,
        ]);
    }

    /** @return array<string, string> */
    private function params(): array
    {
        return ['client_id' => $this->client->id, 'cleaning_object_id' => $this->object->id];
    }

    public function test_preview_returns_buyer_and_ordered_deduped_email_options(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $this->contact($this->object, 'objekt-b@example.com');
        $this->contact($this->object, 'objekt-a@example.com', true);
        $this->contact($this->client, 'faktury@example.com', true);
        $this->contact($this->client, 'klient@example.com');

        $response = $this->getJson(route('invoices.buyer-preview', $this->params()))->assertOk();

        $response->assertJsonPath('buyer.source', 'object_billing')
            ->assertJsonPath('buyer.name', 'Dom Lipova')
            ->assertJsonPath('buyer.represented_by', 'Spravca s.r.o.')
            ->assertJsonPath('buyer.email', null)
            ->assertJsonPath('email_options', [
                'Faktury@Example.com',
                'objekt-a@example.com',
                'objekt-b@example.com',
                'klient@example.com',
            ]);
    }

    public function test_preview_without_object_uses_the_client(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $this->contact($this->client, 'klient@example.com');

        $this->getJson(route('invoices.buyer-preview', ['client_id' => $this->client->id, 'cleaning_object_id' => '']))
            ->assertOk()
            ->assertJsonPath('buyer.source', 'client')
            ->assertJsonPath('email_options', ['klient@example.com']);
    }

    public function test_options_are_gated_by_view_all_objects_and_view_clients(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $this->contact($this->object, 'objekt@example.com');
        $this->contact($this->client, 'klient@example.com');
        $this->revoke([PermissionEnum::ViewAllObjects]);

        $this->getJson(route('invoices.buyer-preview', $this->params()))
            ->assertOk()
            ->assertJsonPath('email_options', ['klient@example.com']);

        $this->revoke([PermissionEnum::ViewClients]);

        $this->getJson(route('invoices.buyer-preview', $this->params()))
            ->assertOk()
            ->assertJsonPath('email_options', []);
    }

    public function test_object_of_another_client_is_rejected(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $other = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->getJson(route('invoices.buyer-preview', ['client_id' => $other->id, 'cleaning_object_id' => $this->object->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cleaning_object_id');
    }

    public function test_client_of_another_company_is_rejected(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $foreignTenant = Tenant::factory()->create();
        $foreign = Client::factory()->create(['tenant_id' => $foreignTenant->id]);

        $this->getJson(route('invoices.buyer-preview', ['client_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_id');
    }

    public function test_missing_client_id_is_rejected(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);

        $this->getJson(route('invoices.buyer-preview'))->assertUnprocessable()->assertJsonValidationErrors('client_id');
    }

    public function test_view_only_permissions_get_403_and_create_recurring_alone_is_enough(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $this->revoke([PermissionEnum::CreateInvoices, PermissionEnum::EditInvoices, PermissionEnum::CreateRecurringInvoices, PermissionEnum::EditRecurringInvoices]);

        $this->getJson(route('invoices.buyer-preview', $this->params()))->assertForbidden();

        $role = Role::inTenant($this->tenant->id)->where('name', 'Admin')->firstOrFail();
        $role->givePermissionTo(PermissionEnum::CreateRecurringInvoices->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson(route('invoices.buyer-preview', $this->params()))->assertOk();
    }
}
