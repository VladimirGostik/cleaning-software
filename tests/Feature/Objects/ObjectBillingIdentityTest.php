<?php

declare(strict_types=1);

namespace Tests\Feature\Objects;

use App\Enums\ObjectTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Role;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\ObjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ObjectBillingIdentityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $billing
     * @return array<string, mixed>
     */
    private function payload(string $clientId, array $billing = []): array
    {
        return array_merge([
            'client_id' => $clientId,
            'type' => ObjectTypeEnum::Office->value,
            'name' => 'Bytovy dom',
            'street' => 'Lipova 5',
            'city' => 'Bratislava',
            'postal_code' => '811 01',
            'country' => 'SK',
            'is_active' => true,
            'contacts' => [],
        ], $billing);
    }

    /** @return array<string, mixed> */
    private function fullBilling(): array
    {
        return [
            'billing_name' => 'Spolocenstvo vlastnikov',
            'billing_ico' => '22222222',
            'billing_dic' => '2022222222',
            'billing_vat_number' => 'SK2022222222',
            'billing_peppol_participant_id' => '0245:2022222222',
            'billing_street' => 'Fakturacna 9',
            'billing_city' => 'Nitra',
            'billing_postal_code' => '94901',
        ];
    }

    private function revokeViewAllObjectsFromSekretarka(Tenant $tenant): void
    {
        $role = Role::inTenant($tenant->id)->where('name', 'Sekretárka')->firstOrFail();
        $role->revokePermissionTo(PermissionEnum::ViewAllObjects->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_stores_full_billing_identity_and_show_exposes_it(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->payload($client->id, $this->fullBilling()))->assertRedirect();

        $object = CleaningObject::where('name', 'Bytovy dom')->firstOrFail();
        $this->assertSame('Spolocenstvo vlastnikov', $object->billing_name);
        $this->assertSame('0245:2022222222', $object->billing_peppol_participant_id);

        $this->get(route('objects.show', $object))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Objects/Show')
                ->where('object.billing.name', 'Spolocenstvo vlastnikov')
                ->where('object.billing.ico', '22222222')
                ->where('object.billing.postal_code', '94901'),
        );
    }

    public function test_object_without_billing_name_exposes_null_billing(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('objects.show', $object))->assertInertia(
            fn (AssertableInertia $page) => $page->where('object.billing', null),
        );
    }

    public function test_update_can_clear_the_billing_identity(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $object = CleaningObject::factory()->withBillingIdentity()->create(['tenant_id' => $tenant->id]);

        $this->put(route('objects.update', $object), $this->payload($object->client_id))->assertRedirect();

        $this->assertFalse($object->refresh()->hasBillingIdentity());
        $this->assertNull($object->billing_ico);
    }

    public function test_billing_field_without_billing_name_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->payload($client->id, ['billing_name' => null, 'billing_ico' => '22222222']))
            ->assertSessionHasErrors('billing_name');
    }

    public function test_invalid_peppol_id_is_rejected_and_dic_scheme_is_accepted(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->payload($client->id, ['billing_name' => 'X', 'billing_peppol_participant_id' => 'x']))
            ->assertSessionHasErrors('billing_peppol_participant_id');

        $this->post(route('objects.store'), $this->payload($client->id, ['billing_name' => 'X', 'billing_peppol_participant_id' => '0245:2012345678']))
            ->assertSessionHasNoErrors();
    }

    public function test_cleaner_with_assigned_job_gets_null_billing_on_show(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $object = CleaningObject::factory()->withBillingIdentity()->create(['tenant_id' => $tenant->id]);
        ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('objects.show', $object));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('object.billing', null));
        $response->assertDontSee((string) $object->billing_name);
    }

    public function test_schedule_object_options_never_carry_the_billing_name(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->actingAsTenantUser('Admin', $tenant);
        CleaningObject::factory()->withBillingIdentity()->create(['tenant_id' => $tenant->id]);

        $options = app(ObjectService::class)->optionsVisibleTo($admin);

        $this->assertCount(1, $options);
        $this->assertNull($options[0]->billing_name);
        $this->get(route('jobs.create'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('context.objects.0.billing_name', null),
        );
    }

    public function test_invoice_form_context_carries_the_billing_name_for_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        CleaningObject::factory()->withBillingIdentity(['billing_name' => 'Dom Lipova'])->create(['tenant_id' => $tenant->id]);

        $this->get(route('invoices.create'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('context.objects.0.billing_name', 'Dom Lipova'),
        );
    }

    public function test_contract_form_context_hides_billing_name_without_view_all_objects(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);
        $this->revokeViewAllObjectsFromSekretarka($tenant);
        CleaningObject::factory()->withBillingIdentity(['billing_name' => 'Dom Lipova'])->create(['tenant_id' => $tenant->id]);

        $this->get(route('contracts.create'))->assertInertia(
            fn (AssertableInertia $page) => $page->where('context.objects.0.billing_name', null),
        );
    }

    public function test_blank_billing_name_is_not_an_identity(): void
    {
        $this->assertFalse((new CleaningObject(['billing_name' => '']))->hasBillingIdentity());
    }

    public function test_required_name_error_uses_the_short_message(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->payload($client->id, ['billing_name' => null, 'billing_dic' => '2022222222']))
            ->assertSessionHasErrors(['billing_name' => __('app.object_billing_name_required')]);
    }

    public function test_actor_without_view_all_objects_cannot_change_billing_on_update(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Sekretárka', $tenant);
        $object = CleaningObject::factory()->withBillingIdentity(['billing_name' => 'Povodny'])->create(['tenant_id' => $tenant->id]);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create(['tenant_id' => $tenant->id]);
        $this->revokeViewAllObjectsFromSekretarka($tenant);

        $this->put(route('objects.update', $object), $this->payload($object->client_id, ['billing_name' => 'Podvrh']))->assertRedirect();

        $this->assertSame('Povodny', $object->refresh()->billing_name);
    }

    public function test_actor_without_view_all_objects_cannot_set_billing_on_store(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);
        $this->revokeViewAllObjectsFromSekretarka($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->payload($client->id, ['billing_name' => 'Podvrh']))->assertRedirect();

        $this->assertNull(CleaningObject::where('name', 'Bytovy dom')->firstOrFail()->billing_name);
    }

    public function test_billing_email_is_stored_without_a_billing_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->payload($client->id, ['billing_email' => 'faktury@example.com']))
            ->assertSessionHasNoErrors();

        $object = CleaningObject::where('name', 'Bytovy dom')->firstOrFail();
        $this->assertSame('faktury@example.com', $object->billing_email);
        $this->assertFalse($object->hasBillingIdentity());
        $this->get(route('objects.show', $object))->assertInertia(
            fn (AssertableInertia $page) => $page->where('object.billing.name', null)->where('object.billing.email', 'faktury@example.com'),
        );
    }

    public function test_invalid_billing_email_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->payload($client->id, ['billing_email' => 'nope']))
            ->assertSessionHasErrors('billing_email');
    }

    public function test_billing_email_is_stripped_without_view_all_objects(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);
        $this->revokeViewAllObjectsFromSekretarka($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('objects.store'), $this->payload($client->id, ['billing_email' => 'faktury@example.com']))->assertRedirect();

        $this->assertNull(CleaningObject::where('name', 'Bytovy dom')->firstOrFail()->billing_email);
    }
}
