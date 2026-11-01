<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Data\Contracts\ContractUpsertData;
use App\Enums\PermissionEnum;
use App\Models\Activity;
use App\Models\Contract;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\ContractService;
use App\Services\Documents\ContractTextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class EmployeePersonalDataTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Mária',
            'last_name' => 'Nováková',
            'phone' => null,
            'position' => null,
            'role_name' => 'Interná upratovačka',
            'permissions' => [],
            'birth_date' => '1990-01-01',
            'national_id' => '9001011234',
            'street' => 'Hlavná 1',
            'city' => 'Bratislava',
            'postal_code' => '811 01',
            'employee_type' => null,
            'contact_email' => null,
            'company_ico' => null,
            'company_name' => null,
        ], $overrides);
    }

    private function actorWithoutPersonalData(Tenant $tenant): User
    {
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        /** @var Role $role */
        $role = Role::inTenant($tenant->id)->where('name', 'Admin')->firstOrFail();
        $role->revokePermissionTo(PermissionEnum::ViewEmployeePersonalData->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $actor->fresh() ?? $actor;
    }

    public function test_national_id_is_normalized_encrypted_and_kept_out_of_the_activity_log(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->put(route('employees.update', $membership), $this->updatePayload())->assertRedirect();

        $raw = (string) DB::table('tenant_memberships')->where('id', $membership->id)->value('national_id');
        $this->assertNotSame('900101/1234', $raw);
        $this->assertStringNotContainsString('900101', $raw);
        $this->assertSame('900101/1234', $membership->fresh()?->national_id);
        $this->assertStringNotContainsString('900101', (string) json_encode(Activity::query()->pluck('properties')->all()));
        $this->assertArrayNotHasKey('national_id', $membership->fresh()?->toArray() ?? []);
    }

    public function test_invalid_national_id_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->put(route('employees.update', $membership), $this->updatePayload(['national_id' => '12-34']))
            ->assertSessionHasErrors('national_id');
        $this->put(route('employees.update', $membership), $this->updatePayload(['national_id' => '900101/1234']))
            ->assertSessionHasNoErrors();
    }

    public function test_show_carries_only_the_masked_value_and_edit_the_full_value_with_an_audit_event(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'national_id' => '900101/1234']);

        $this->get(route('employees.show', $membership))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('personal.national_id_masked', '******/1234')
            ->missing('personal.national_id'));

        $this->get(route('employees.edit', $membership))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('personal.national_id', '900101/1234'));

        $event = Activity::query()->where('event', 'national_id_viewed')->firstOrFail();
        $this->assertSame($membership->id, $event->subject_id);
        $this->assertStringNotContainsString('900101', (string) json_encode($event->properties));
    }

    public function test_list_dtos_never_contain_personal_data(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'national_id' => '900101/1234']);

        $json = $this->get(route('employees.index'))->getContent();

        $this->assertStringNotContainsString('900101', (string) $json);
        $this->assertStringNotContainsString('national_id', (string) $json);
    }

    public function test_without_the_permission_personal_is_null_and_writes_are_ignored(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actorWithoutPersonalData($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'national_id' => '900101/1234', 'street' => 'Pôvodná']);

        $this->get(route('employees.show', $membership))->assertInertia(fn (AssertableInertia $page) => $page->where('personal', null));
        $this->get(route('employees.edit', $membership))->assertInertia(fn (AssertableInertia $page) => $page->where('personal', null));
        $this->assertDatabaseMissing('activity_log', ['event' => 'national_id_viewed']);

        $this->put(route('employees.update', $membership), $this->updatePayload(['national_id' => '8001015678', 'street' => 'Nová']))->assertRedirect();

        $fresh = $membership->fresh();
        $this->assertSame('900101/1234', $fresh?->national_id);
        $this->assertSame('Pôvodná', $fresh?->street);
        $this->assertSame('Mária', $fresh?->first_name);
    }

    public function test_employee_subject_contract_text_is_gated_by_the_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actorWithoutPersonalData($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'national_id' => '900101/1234']);
        $contract = Contract::factory()->forMembership($membership)->withBody('<p>{{employee.national_id}}</p>')->create(['tenant_id' => $tenant->id]);

        $this->get(route('contracts.show', $contract))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contract.body', null)
            ->where('contract.can_be_signed', false)
            ->where('contract.is_editable', false));
        $this->get(route('contracts.pdf', $contract))->assertForbidden();
        $this->get(route('contracts.edit', $contract))->assertForbidden();
        $this->post(route('contracts.sign', $contract))->assertForbidden();
        $this->delete(route('contracts.destroy', $contract))->assertForbidden();
        $this->assertFalse(app(ContractTextService::class)->canReadText($contract, $actor));
    }

    public function test_generate_resolves_the_national_id_only_for_a_permitted_actor(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'national_id' => '900101/1234']);
        $payload = [
            'category' => 'employment', 'term_type' => 'indefinite', 'contractable_type' => 'tenant_membership',
            'contractable_id' => $membership->id, 'valid_from' => now()->toDateString(), 'show_header' => true, 'show_footer' => true,
            'body' => '<p>{{employee.national_id}}</p>', 'employment' => ['employment_type' => 'tpp'],
        ];

        $this->postJson(route('contracts.generate'), $payload)->assertOk()->assertJsonPath('html', '<p>900101/1234</p>');

        $this->actorWithoutPersonalData($tenant);
        $this->postJson(route('contracts.generate'), $payload)->assertForbidden();
    }

    public function test_service_refuses_an_actor_without_the_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actorWithoutPersonalData($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(AuthorizationException::class);

        app(ContractService::class)->create(ContractUpsertData::from([
            'title' => 'T', 'number' => null, 'category' => 'employment', 'term_type' => 'indefinite',
            'contractable_type' => 'tenant_membership', 'contractable_id' => $membership->id,
            'document_template_id' => null, 'body' => '<p>x</p>', 'valid_from' => now()->toDateString(),
            'end_date' => null, 'notes' => null, 'employment' => ['employment_type' => 'tpp'],
        ]), $actor);
    }

    public function test_accountant_keeps_reading_employment_contracts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forMembership($membership)->create(['tenant_id' => $tenant->id]);

        $this->get(route('contracts.show', $contract))->assertInertia(fn (AssertableInertia $page) => $page->whereNot('contract.body', null));
    }
}
