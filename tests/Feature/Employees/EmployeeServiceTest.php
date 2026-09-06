<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Data\Employees\EmployeeStoreData;
use App\Data\Employees\EmployeeUpdateData;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Enums\JobStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\InvitationCreated;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EmployeeServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $overrides */
    private function storeData(array $overrides = []): EmployeeStoreData
    {
        return EmployeeStoreData::from(array_merge([
            'email' => 'newcleaner@example.com',
            'first_name' => 'Nová',
            'last_name' => 'Upratovačka',
            'phone' => null,
            'position' => null,
            'role_name' => 'Interná upratovačka',
            'permissions' => [],
            'employment' => null,
        ], $overrides));
    }

    public function test_create_new_user_has_null_password_and_dispatches_invitation(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);

        $membership = app(EmployeeService::class)->create($this->storeData(), $actor);

        $user = User::where('email', 'newcleaner@example.com')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertTrue($membership->is_active);
        $this->assertSame('newcleaner@example.com', $user->email);

        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenant->id,
            'email' => 'newcleaner@example.com',
        ]);
        Notification::assertSentOnDemand(
            InvitationCreated::class,
            fn (InvitationCreated $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'newcleaner@example.com',
        );
    }

    public function test_create_links_existing_user_without_invitation_or_new_row(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $existing = User::factory()->create(['email' => 'existing-employee@example.com']);

        app(EmployeeService::class)->create($this->storeData(['email' => 'existing-employee@example.com']), $actor);

        $this->assertSame(1, User::where('email', 'existing-employee@example.com')->count());
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $existing->id, 'tenant_id' => $tenant->id, 'is_active' => true]);
        $this->assertDatabaseMissing('tenant_invitations', ['email' => 'existing-employee@example.com']);
        Notification::assertNothingSent();
    }

    public function test_create_throws_when_already_an_active_member(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $existing = User::factory()->create(['email' => 'already@example.com']);
        TenantMembership::create(['user_id' => $existing->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        $this->expectException(ValidationException::class);

        app(EmployeeService::class)->create($this->storeData(['email' => 'already@example.com']), $actor);
    }

    public function test_create_reactivates_a_deactivated_membership(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $existing = User::factory()->create(['email' => 'rejoin@example.com']);
        TenantMembership::create(['user_id' => $existing->id, 'tenant_id' => $tenant->id, 'is_active' => false, 'joined_at' => now()->subYear()]);

        $membership = app(EmployeeService::class)->create($this->storeData(['email' => 'rejoin@example.com']), $actor);

        $this->assertTrue($membership->is_active);
        $this->assertSame(1, TenantMembership::where('user_id', $existing->id)->where('tenant_id', $tenant->id)->count());
    }

    public function test_deactivate_sets_inactive_and_unassigns_future_planned_jobs_only(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $futurePlanned = ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->addDays(5)->toDateString(),
        ]);
        $pastPlanned = ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->subDays(5)->toDateString(),
        ]);
        $futureCompleted = ScheduledJob::factory()->assignedTo($membership)->completed()->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
        ]);

        $unassigned = app(EmployeeService::class)->deactivate($membership);

        $membership->refresh();
        $futurePlanned->refresh();
        $pastPlanned->refresh();
        $futureCompleted->refresh();

        $this->assertSame(1, $unassigned);
        $this->assertFalse($membership->is_active);
        $this->assertSame(JobStatusEnum::Unassigned, $futurePlanned->status);
        $this->assertSame($membership->id, $pastPlanned->assigned_membership_id);
        $this->assertSame($membership->id, $futureCompleted->assigned_membership_id);
    }

    public function test_reactivate_sets_active_true(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

        app(EmployeeService::class)->reactivate($membership);

        $membership->refresh();
        $this->assertTrue($membership->is_active);
    }

    public function test_create_with_employment_creates_draft_employment_contract(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);

        $membership = app(EmployeeService::class)->create($this->storeData([
            'employment' => [
                'employment_type' => EmploymentContractTypeEnum::Tpp->value,
                'position' => 'Upratovačka',
                'hourly_rate' => null,
                'monthly_salary' => 900,
                'weekly_hours' => 40,
                'probation_end_date' => null,
            ],
        ]), $actor);

        $contract = Contract::where('contractable_id', $membership->id)->where('category', ContractCategoryEnum::Employment->value)->firstOrFail();
        $this->assertSame(ContractStatusEnum::Draft, $contract->status);
        $this->assertNotNull($contract->employmentContract);
        $this->assertSame(EmploymentContractTypeEnum::Tpp, $contract->employmentContract->employment_type);
    }

    public function test_create_with_employment_uses_first_active_employment_template_body(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $template = ContractTemplate::factory()->create([
            'tenant_id' => $tenant->id,
            'category' => ContractCategoryEnum::Employment,
            'is_active' => true,
            'name' => 'AAA Template',
            'body' => 'Pracovná zmluva pre {{employee.name}}.',
        ]);

        $membership = app(EmployeeService::class)->create($this->storeData([
            'employment' => [
                'employment_type' => EmploymentContractTypeEnum::Dpp->value,
                'position' => null,
                'hourly_rate' => 5,
                'monthly_salary' => null,
                'weekly_hours' => null,
                'probation_end_date' => null,
            ],
        ]), $actor);

        $contract = Contract::where('contractable_id', $membership->id)->where('category', ContractCategoryEnum::Employment->value)->firstOrFail();
        $this->assertSame($template->id, $contract->contract_template_id);
    }

    public function test_create_throws_when_actor_grants_permission_they_do_not_have(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Sekretárka', $tenant);

        $this->expectException(ValidationException::class);

        app(EmployeeService::class)->create($this->storeData([
            'role_name' => 'Interná upratovačka',
            'permissions' => ['manage billing settings'],
        ]), $actor);

        $this->assertDatabaseMissing('users', ['email' => 'newcleaner@example.com']);
    }

    public function test_create_throws_when_actor_assigns_role_beyond_own_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->expectException(ValidationException::class);

        app(EmployeeService::class)->create($this->storeData(['role_name' => 'Admin']), $actor);

        $this->assertDatabaseMissing('users', ['email' => 'newcleaner@example.com']);
    }

    public function test_update_wipes_direct_permissions_when_empty_array_given(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $membership = app(EmployeeService::class)->create($this->storeData(['permissions' => ['view objects']]), $actor);
        $user = User::findOrFail($membership->user_id);
        $this->assertNotEmpty($user->getDirectPermissions());

        app(EmployeeService::class)->update($membership, EmployeeUpdateData::from([
            'first_name' => 'Nová',
            'last_name' => 'Upratovačka',
            'phone' => null,
            'position' => null,
            'role_name' => 'Interná upratovačka',
            'permissions' => [],
        ]), $actor);

        $this->assertEmpty(User::findOrFail($membership->user_id)->getDirectPermissions());
    }

    public function test_update_persists_profile_fields_and_role(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $membership = app(EmployeeService::class)->create($this->storeData(), $actor);

        $updated = app(EmployeeService::class)->update($membership, EmployeeUpdateData::from([
            'first_name' => 'Zmenené',
            'last_name' => 'Meno',
            'phone' => '0900123456',
            'position' => 'Vedúca zmeny',
            'role_name' => 'Vedúca',
            'permissions' => [],
        ]), $actor);

        $this->assertSame('Zmenené', $updated->first_name);
        $this->assertSame('Meno', $updated->last_name);
        $this->assertSame('0900123456', $updated->phone);
        $this->assertSame('Vedúca zmeny', $updated->position);
        $this->assertTrue(User::findOrFail($updated->user_id)->hasRole('Vedúca'));
    }
}
