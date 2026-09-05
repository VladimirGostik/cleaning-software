<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Data\Employees\EmployeeUpsertData;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class EmployeeServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // create — happy path: new user
    // -------------------------------------------------------------------------

    public function test_create_new_user_and_membership(): void
    {
        $actor = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $actor->id)->first();

        $data = EmployeeUpsertData::from([
            'email' => 'newclean@example.com',
            'first_name' => 'Jana',
            'last_name' => 'Novak',
            'phone' => '+421900000001',
            'role_name' => 'Interná upratovačka',
            'permissions' => [],
        ]);

        $membership = app(EmployeeService::class)->create($data);

        $this->assertDatabaseHas('users', ['email' => 'newclean@example.com']);
        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'first_name' => 'Jana',
            'last_name' => 'Novak',
            'is_active' => true,
        ]);
        $this->assertSame('Jana Novak', $membership->display_name);

        $user = User::where('email', 'newclean@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Interná upratovačka'));
    }

    // -------------------------------------------------------------------------
    // create — happy path: link existing user
    // -------------------------------------------------------------------------

    public function test_create_links_existing_user_without_creating_duplicate(): void
    {
        $actor = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $actor->id)->first();

        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $data = EmployeeUpsertData::from([
            'email' => 'existing@example.com',
            'first_name' => 'Peter',
            'last_name' => 'Kral',
            'phone' => null,
            'role_name' => 'Sekretárka',
            'permissions' => [],
        ]);

        $membership = app(EmployeeService::class)->create($data);

        $this->assertSame($existingUser->id, $membership->user_id);
        $this->assertDatabaseCount('users', 2);
        $this->assertSame('Peter Kral', $membership->display_name);
    }

    // -------------------------------------------------------------------------
    // create — fail: already active member
    // -------------------------------------------------------------------------

    public function test_create_throws_when_user_is_already_active_member(): void
    {
        $actor = $this->actingAsTenantUser('Admin');

        $this->expectException(ValidationException::class);

        $data = EmployeeUpsertData::from([
            'email' => $actor->email,
            'first_name' => null,
            'last_name' => null,
            'phone' => null,
            'role_name' => 'Admin',
            'permissions' => [],
        ]);

        app(EmployeeService::class)->create($data);
    }

    // -------------------------------------------------------------------------
    // create — happy path: reactivate deactivated membership
    // -------------------------------------------------------------------------

    public function test_create_reactivates_deactivated_membership(): void
    {
        $actor = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $actor->id)->first();

        $targetUser = User::factory()->create(['email' => 'reactivate@example.com']);
        TenantMembership::create([
            'user_id' => $targetUser->id,
            'tenant_id' => $tenant->id,
            'is_active' => false,
            'joined_at' => now()->subMonth(),
        ]);

        $data = EmployeeUpsertData::from([
            'email' => 'reactivate@example.com',
            'first_name' => 'Anna',
            'last_name' => 'Hruzova',
            'phone' => null,
            'role_name' => 'Interná upratovačka',
            'permissions' => [],
        ]);

        $membership = app(EmployeeService::class)->create($data);

        $this->assertTrue($membership->is_active);
        $this->assertSame($targetUser->id, $membership->user_id);
        $this->assertDatabaseCount('tenant_memberships', 2); // owner + reactivated
    }

    // -------------------------------------------------------------------------
    // deactivate — happy path
    // -------------------------------------------------------------------------

    public function test_deactivate_sets_is_active_false(): void
    {
        $actor = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $actor->id)->first();

        $targetUser = User::factory()->create();
        $membership = TenantMembership::create([
            'user_id' => $targetUser->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        app(EmployeeService::class)->deactivate($membership);

        $this->assertFalse($membership->refresh()->is_active);
    }

    // -------------------------------------------------------------------------
    // create — happy path: employment contract optional
    // -------------------------------------------------------------------------

    public function test_create_with_employment_contract_creates_contract_child(): void
    {
        $actor = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $actor->id)->first();

        $data = EmployeeUpsertData::from([
            'email' => 'withcontract@example.com',
            'first_name' => 'Maria',
            'last_name' => 'Slobodova',
            'phone' => null,
            'role_name' => 'Interná upratovačka',
            'permissions' => [],
            'employment' => [
                'employment_type' => EmploymentContractTypeEnum::Dpp->value,
                'position' => 'Senior cleaner',
                'hourly_rate' => 7.50,
            ],
        ]);

        $membership = app(EmployeeService::class)->create($data);

        $contract = Contract::where('contractable_type', 'tenant_membership')
            ->where('contractable_id', $membership->id)
            ->where('category', ContractCategoryEnum::Employment->value)
            ->first();

        $this->assertNotNull($contract);
        $this->assertSame(ContractStatusEnum::Draft, $contract->status);
        $this->assertNotNull($contract->employmentContract);
        $this->assertSame('dpp', $contract->employmentContract->employment_type->value);
    }

    // -------------------------------------------------------------------------
    // create — security: permission escalation blocked
    // -------------------------------------------------------------------------

    public function test_create_does_not_grant_permission_actor_lacks(): void
    {
        // Arrange — actor is Interná upratovačka (has only view schedule + view objects)
        $actor = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $actor->id)->first();

        $data = EmployeeUpsertData::from([
            'email' => 'perm_escalation@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => null,
            'role_name' => 'Interná upratovačka',
            // Actor doesn't hold manage billing settings — should be filtered out
            'permissions' => [PermissionEnum::ManageBillingSettings->value],
        ]);

        // Act
        app(EmployeeService::class)->create($data);

        // Assert — new user has no direct permissions in this tenant
        $newUser = User::where('email', 'perm_escalation@example.com')->firstOrFail();

        $this->assertDatabaseMissing('model_has_permissions', [
            'model_id' => $newUser->id,
            'model_type' => User::class,
            'tenant_id' => $tenant->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // create — security: role escalation blocked
    // -------------------------------------------------------------------------

    public function test_create_throws_when_role_escalation_attempted(): void
    {
        // Arrange — actor is Interná upratovačka (has only view schedule + view objects)
        $actor = $this->actingAsTenantUser('Interná upratovačka');

        $this->expectException(ValidationException::class);

        $data = EmployeeUpsertData::from([
            'email' => 'role_escalation@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => null,
            // Admin holds ALL permissions — a superset of Interná upratovačka's 2 perms
            'role_name' => 'Admin',
            'permissions' => [],
        ]);

        // Act — must throw before creating the user
        app(EmployeeService::class)->create($data);
    }

    public function test_role_escalation_does_not_create_user(): void
    {
        // Arrange
        $actor = $this->actingAsTenantUser('Interná upratovačka');

        $data = EmployeeUpsertData::from([
            'email' => 'no_user_created@example.com',
            'first_name' => 'Ghost',
            'last_name' => 'User',
            'phone' => null,
            'role_name' => 'Admin',
            'permissions' => [],
        ]);

        try {
            app(EmployeeService::class)->create($data);
        } catch (ValidationException) {
            // expected
        }

        // Act + Assert — no user was persisted (fail-fast before user creation)
        $this->assertDatabaseMissing('users', ['email' => 'no_user_created@example.com']);
    }

    // -------------------------------------------------------------------------
    // update — correctness: empty permissions array clears direct overrides
    // -------------------------------------------------------------------------

    public function test_update_clears_permissions_when_empty_array_submitted(): void
    {
        // Arrange
        $actor = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $actor->id)->first();

        $targetUser = User::factory()->create();
        $membership = TenantMembership::create([
            'user_id' => $targetUser->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // Give the target user a direct permission in this tenant
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $targetUser->givePermissionTo(PermissionEnum::ViewClients->value);

        $viewClientsPerm = Permission::where('name', PermissionEnum::ViewClients->value)->firstOrFail();
        $this->assertDatabaseHas('model_has_permissions', [
            'permission_id' => $viewClientsPerm->id,
            'model_id' => $targetUser->id,
            'tenant_id' => $tenant->id,
        ]);

        $membership->load('user');
        $data = EmployeeUpsertData::from([
            'email' => $targetUser->email,
            'first_name' => 'Anna',
            'last_name' => 'Hruzova',
            'phone' => null,
            'role_name' => 'Interná upratovačka',
            'permissions' => [], // explicit empty — must wipe direct permissions
        ]);

        // Act
        app(EmployeeService::class)->update($membership, $data);

        // Assert — direct permission row is gone
        $this->assertDatabaseMissing('model_has_permissions', [
            'permission_id' => $viewClientsPerm->id,
            'model_id' => $targetUser->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // update — happy path: profile + role change persists
    // -------------------------------------------------------------------------

    public function test_update_persists_profile_and_role_changes(): void
    {
        // Arrange
        $actor = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $actor->id)->first();

        $targetUser = User::factory()->create();
        $membership = TenantMembership::create([
            'user_id' => $targetUser->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
            'first_name' => 'Old',
            'last_name' => 'Name',
            'phone' => null,
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $targetUser->assignRole('Interná upratovačka');

        $membership->load('user');
        $data = EmployeeUpsertData::from([
            'email' => $targetUser->email,
            'first_name' => 'New',
            'last_name' => 'Lastname',
            'phone' => '+421900999888',
            'role_name' => 'Sekretárka',
            'permissions' => [],
        ]);

        // Act
        $updated = app(EmployeeService::class)->update($membership, $data);

        // Assert
        $this->assertSame('New', $updated->first_name);
        $this->assertSame('Lastname', $updated->last_name);
        $this->assertSame('+421900999888', $updated->phone);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->assertTrue($targetUser->fresh()->hasRole('Sekretárka'));
        $this->assertFalse($targetUser->fresh()->hasRole('Interná upratovačka'));
    }
}
