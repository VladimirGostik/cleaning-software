<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'controllertest@example.com',
            'first_name' => 'Meno',
            'last_name' => 'Priezvisko',
            'phone' => null,
            'position' => null,
            'role_name' => 'Interná upratovačka',
            'permissions' => [],
            'employment' => null,
        ], $overrides);
    }

    public function test_index_renders_employees_with_filter_options(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        TenantMembership::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('employees.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Employees/Index', shouldExist: false)
                ->has('employees.data')
                ->has('filterOptions.roles'),
        );
    }

    public function test_index_forbidden_without_view_employees_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->get(route('employees.index'))->assertForbidden();
    }

    public function test_create_renders_form_context(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('employees.create'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Employees/Create', shouldExist: false)
                ->has('context.roles')
                ->has('context.permission_groups'),
        );
    }

    public function test_store_creates_employee_and_redirects_to_show(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->post(route('employees.store'), $this->storePayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'controllertest@example.com']);
    }

    public function test_show_renders_employee_detail_with_can_map(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('employees.show', $membership));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Employees/Show', shouldExist: false)
                ->where('employee.id', $membership->id)
                ->where('employee.can.update', true)
                ->where('employee.can.deactivate', true),
        );
    }

    public function test_show_cross_tenant_membership_returns_403(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $foreignMembership = TenantMembership::factory()->create(['tenant_id' => $tenantB->id]);
        $this->actingAsTenantUser('Admin', $tenantA);

        $this->get(route('employees.show', $foreignMembership))->assertForbidden();
    }

    public function test_edit_returns_employee_and_context(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('employees.edit', $membership));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Employees/Edit', shouldExist: false)
                ->where('employee.id', $membership->id),
        );
    }

    public function test_update_persists_changes_and_redirects(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->put(route('employees.update', $membership), [
            'first_name' => 'Upravené',
            'last_name' => 'Meno',
            'phone' => null,
            'position' => null,
            'role_name' => 'Interná upratovačka',
            'permissions' => [],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenant_memberships', ['id' => $membership->id, 'first_name' => 'Upravené']);
    }

    public function test_update_cross_tenant_membership_returns_403(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $foreignMembership = TenantMembership::factory()->create(['tenant_id' => $tenantB->id]);
        $this->actingAsTenantUser('Admin', $tenantA);

        $this->put(route('employees.update', $foreignMembership), [
            'first_name' => 'X',
            'last_name' => 'Y',
            'phone' => null,
            'position' => null,
            'role_name' => 'Interná upratovačka',
            'permissions' => [],
        ])->assertForbidden();
    }

    public function test_deactivate_sets_inactive_and_redirects(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $response = $this->post(route('employees.deactivate', $membership));

        $response->assertRedirect();
        $this->assertDatabaseHas('tenant_memberships', ['id' => $membership->id, 'is_active' => false]);
    }

    public function test_deactivate_cross_tenant_membership_returns_403(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $foreignMembership = TenantMembership::factory()->create(['tenant_id' => $tenantB->id]);
        $this->actingAsTenantUser('Admin', $tenantA);

        $this->post(route('employees.deactivate', $foreignMembership))->assertForbidden();
    }

    public function test_deactivate_self_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $ownMembership = TenantMembership::query()->where('user_id', $user->id)->where('tenant_id', $tenant->id)->firstOrFail();

        $this->post(route('employees.deactivate', $ownMembership))->assertForbidden();
    }

    public function test_reactivate_sets_active_and_redirects(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

        $response = $this->post(route('employees.reactivate', $membership));

        $response->assertRedirect();
        $this->assertDatabaseHas('tenant_memberships', ['id' => $membership->id, 'is_active' => true]);
    }

    public function test_role_assign_updates_role_and_redirects(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vedúca', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::findOrFail($membership->user_id);

        $response = $this->post(route('employees.role', $membership), ['role_name' => 'Interná upratovačka']);

        $response->assertRedirect();
        $this->assertTrue(User::findOrFail($user->id)->hasRole('Interná upratovačka'));
    }

    public function test_role_assign_forbidden_without_assign_employees_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('employees.role', $membership), ['role_name' => 'Interná upratovačka'])->assertForbidden();
    }
}
