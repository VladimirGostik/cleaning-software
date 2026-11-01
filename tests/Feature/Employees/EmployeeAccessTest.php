<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Enums\InvitationStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\TaskStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\DeviceToken;
use App\Models\Role;
use App\Models\ScheduledJob;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\InvitationCreated;
use App\Scopes\TenantScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CallsMcp;
use Tests\Support\CreatesTaskFixtures;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class EmployeeAccessTest extends TestCase
{
    use CallsMcp;
    use CreatesTaskFixtures;
    use CreatesUsers;
    use RefreshDatabase;

    private Tenant $firm;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->firm = $this->firm('Alpha');
        $this->admin = $this->member($this->firm, 'Admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'has_app_access' => false,
            'employee_type' => 'brigade',
            'company_ico' => null,
            'company_name' => null,
            'email' => null,
            'first_name' => 'Jana',
            'last_name' => 'Nováková',
            'phone' => null,
            'position' => null,
            'role_name' => null,
            'permissions' => [],
            'employment' => null,
        ], $overrides);
    }

    private function employeeWithoutLogin(): TenantMembership
    {
        return TenantMembership::factory()->withProfile()->withoutUser()->create([
            'tenant_id' => $this->firm->id,
            'contact_email' => 'kontakt@example.com',
        ]);
    }

    private function employeeWithLogin(string $role = 'Interná upratovačka', ?Tenant $tenant = null, bool $pending = false): TenantMembership
    {
        $tenant ??= $this->firm;
        $user = $this->member($tenant, $role, User::factory()->create($pending ? ['password' => null] : []));

        return $this->membershipOf($user, $tenant);
    }

    private function tenantOf(User $user): Tenant
    {
        return Tenant::query()->whereKey(TenantMembership::query()->where('user_id', $user->id)->value('tenant_id'))->firstOrFail();
    }

    private function asAdmin(): static
    {
        return $this->webAs($this->admin, $this->firm);
    }

    /** @return list<string> */
    private function roleNames(User $user, Tenant $tenant): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $names = [];

        foreach ($user->roles()->get() as $role) {
            /** @var Role $role */
            $names[] = $role->name;
        }

        return $names;
    }

    private function pendingInvitation(string $email): ?TenantInvitation
    {
        return TenantInvitation::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->firm->id)
            ->where('email', $email)
            ->where('status', InvitationStatusEnum::Pending->value)
            ->first();
    }

    public function test_admin_creates_an_employee_without_access_and_nothing_login_related_is_written(): void
    {
        Notification::fake();
        $usersBefore = User::count();

        $this->asAdmin()->post(route('employees.store'), $this->storePayload(['email' => 'kontakt@example.com']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $membership = TenantMembership::query()->where('tenant_id', $this->firm->id)->where('first_name', 'Jana')->firstOrFail();
        $this->assertNull($membership->user_id);
        $this->assertSame('kontakt@example.com', $membership->contact_email);
        $this->assertSame($usersBefore, User::count());
        $this->assertNull($this->pendingInvitation('kontakt@example.com'));
        Notification::assertNothingSent();
    }

    public function test_creating_without_access_requires_first_and_last_name(): void
    {
        $this->asAdmin()->post(route('employees.store'), $this->storePayload(['first_name' => null, 'last_name' => null]))
            ->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_creating_without_access_allows_a_missing_email(): void
    {
        $this->asAdmin()->post(route('employees.store'), $this->storePayload())->assertSessionHasNoErrors();

        $membership = TenantMembership::query()->where('first_name', 'Jana')->firstOrFail();
        $this->assertNull($membership->contact_email);
        $this->assertNull($membership->user_id);
    }

    public function test_creating_with_access_creates_user_role_and_invitation(): void
    {
        Notification::fake();

        $this->asAdmin()->post(route('employees.store'), $this->storePayload([
            'has_app_access' => true,
            'email' => 'nova@example.com',
            'first_name' => null,
            'last_name' => null,
            'role_name' => 'Interná upratovačka',
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', 'nova@example.com')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertSame(['Interná upratovačka'], $this->roleNames($user, $this->firm));
        $this->assertSame($user->id, $this->membershipOf($user, $this->firm)->user_id);
        $this->assertNotNull($this->pendingInvitation('nova@example.com'));
        Notification::assertSentOnDemand(InvitationCreated::class);
    }

    public function test_creating_with_access_requires_email_and_role(): void
    {
        $this->asAdmin()->post(route('employees.store'), $this->storePayload(['has_app_access' => true]))
            ->assertSessionHasErrors(['email', 'role_name']);
    }

    public function test_creating_with_access_rejects_an_active_member_email(): void
    {
        $existing = $this->employeeWithLogin();

        $this->asAdmin()->post(route('employees.store'), $this->storePayload([
            'has_app_access' => true,
            'email' => $existing->user?->email,
            'role_name' => 'Interná upratovačka',
        ]))->assertSessionHasErrors('email');
    }

    public function test_an_actor_without_manage_employee_access_cannot_create_with_access_and_nothing_is_written(): void
    {
        $user = $this->userWithPermission(PermissionEnum::ViewEmployees->value, PermissionEnum::CreateEmployees->value);
        $roleName = $this->roleNames($user, $this->tenantOf($user))[0];

        $this->actingAs($user);

        $this->post(route('employees.store'), $this->storePayload([
            'has_app_access' => true,
            'email' => 'blocked@example.com',
            'role_name' => $roleName,
        ]))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
        $this->assertDatabaseMissing('tenant_memberships', ['first_name' => 'Jana']);
    }

    public function test_an_actor_without_manage_employee_access_can_create_without_access(): void
    {
        $user = $this->userWithPermission(PermissionEnum::ViewEmployees->value, PermissionEnum::CreateEmployees->value);
        $this->actingAs($user);

        $this->post(route('employees.store'), $this->storePayload())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tenant_memberships', ['first_name' => 'Jana', 'user_id' => null]);
    }

    public function test_grant_with_a_new_email_creates_user_role_and_invitation(): void
    {
        Notification::fake();
        $employee = $this->employeeWithoutLogin();

        $this->asAdmin()->post(route('employees.access.store', $employee), [
            'email' => 'login@example.com',
            'role_name' => 'Sekretárka',
            'permissions' => [],
        ])->assertRedirect(route('employees.show', $employee))->assertSessionHas('success', __('app.employee_access_invited'));

        $employee->refresh();
        $user = User::where('email', 'login@example.com')->firstOrFail();
        $this->assertSame($user->id, $employee->user_id);
        $this->assertNull($employee->contact_email);
        $this->assertSame(['Sekretárka'], $this->roleNames($user, $this->firm));
        $this->assertNotNull($this->pendingInvitation('login@example.com'));
        Notification::assertSentOnDemand(InvitationCreated::class);
        $this->assertTrue(Activity::query()->where('event', 'employee_access_granted')->where('subject_id', $employee->id)->exists());
    }

    public function test_grant_links_an_existing_account_without_sending_mail(): void
    {
        Notification::fake();
        $employee = $this->employeeWithoutLogin();
        $existing = User::factory()->create(['email' => 'known@example.com']);

        $this->asAdmin()->post(route('employees.access.store', $employee), [
            'email' => 'known@example.com',
            'role_name' => 'Interná upratovačka',
        ])->assertRedirect()->assertSessionHas('success', __('app.employee_access_granted'));

        $this->assertSame($existing->id, $employee->refresh()->user_id);
        Notification::assertNothingSent();
    }

    public function test_grant_rejects_the_email_of_another_member_of_the_firm(): void
    {
        $employee = $this->employeeWithoutLogin();
        $other = $this->employeeWithLogin();

        $this->asAdmin()->post(route('employees.access.store', $employee), [
            'email' => $other->user?->email,
            'role_name' => 'Interná upratovačka',
        ])->assertSessionHasErrors(['email' => __('app.employee_access_email_taken')]);

        $this->assertNull($employee->refresh()->user_id);
    }

    public function test_grant_rejects_a_role_or_permissions_above_the_actors_own(): void
    {
        $limited = $this->userWithPermission(PermissionEnum::ManageEmployeeAccess->value);
        $limitedTenant = $this->tenantOf($limited);
        $target = TenantMembership::factory()->withoutUser()->create(['tenant_id' => $limitedTenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($limitedTenant->id);
        $bigRole = Role::create(['name' => 'Big', 'guard_name' => 'web']);
        $bigRole->givePermissionTo(PermissionEnum::ViewInvoices->value);
        $ownRole = $this->roleNames($limited, $limitedTenant)[0];
        $this->actingAs($limited);

        $this->post(route('employees.access.store', $target), ['email' => 'x@example.com', 'role_name' => 'Big'])
            ->assertSessionHasErrors('roles');
        $this->post(route('employees.access.store', $target), [
            'email' => 'x@example.com',
            'role_name' => $ownRole,
            'permissions' => [PermissionEnum::ViewInvoices->value],
        ])->assertSessionHasErrors('permissions');

        $this->assertNull($target->refresh()->user_id);
    }

    public function test_grant_to_an_inactive_employee_is_forbidden(): void
    {
        $employee = TenantMembership::factory()->withoutUser()->inactive()->create(['tenant_id' => $this->firm->id]);

        $this->asAdmin()->post(route('employees.access.store', $employee), [
            'email' => 'late@example.com',
            'role_name' => 'Interná upratovačka',
        ])->assertForbidden();
    }

    public function test_grant_to_an_employee_who_already_has_a_login_is_forbidden(): void
    {
        $employee = $this->employeeWithLogin();

        $this->asAdmin()->post(route('employees.access.store', $employee), [
            'email' => 'dup@example.com',
            'role_name' => 'Interná upratovačka',
        ])->assertForbidden();
    }

    public function test_vedúca_cannot_grant_revoke_or_resend(): void
    {
        $coordinator = $this->member($this->firm, 'Vedúca');
        $noLogin = $this->employeeWithoutLogin();
        $withLogin = $this->employeeWithLogin();
        $pending = $this->employeeWithLogin(pending: true);

        $web = $this->webAs($coordinator, $this->firm);
        $web->post(route('employees.access.store', $noLogin), ['email' => 'v@example.com', 'role_name' => 'Interná upratovačka'])->assertForbidden();
        $web->delete(route('employees.access.destroy', $withLogin))->assertForbidden();
        $web->post(route('employees.access.invitation', $pending))->assertForbidden();
    }

    public function test_revoke_keeps_the_record_contracts_files_and_jobs_and_clears_roles_in_this_firm_only(): void
    {
        $other = $this->firm('Beta');
        $employee = $this->employeeWithLogin();
        $user = $employee->user;
        $this->assertNotNull($user);
        $this->member($other, 'Sekretárka', $user);
        $contract = Contract::factory()->forMembership($employee)->create(['tenant_id' => $this->firm->id]);
        $client = Client::factory()->create(['tenant_id' => $this->firm->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $this->firm->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->assignedTo($employee)->forObject($object)->create(['tenant_id' => $this->firm->id]);

        $this->asAdmin()->delete(route('employees.access.destroy', $employee))
            ->assertRedirect(route('employees.show', $employee))
            ->assertSessionHas('success', __('app.employee_access_revoked'));

        $employee->refresh();
        $this->assertNull($employee->user_id);
        $this->assertSame($user->email, $employee->contact_email);
        $this->assertTrue($employee->is_active);
        $this->assertSame([], $this->roleNames($user, $this->firm));
        $this->assertSame(['Sekretárka'], $this->roleNames($user, $other));
        $this->assertNotNull(Contract::query()->find($contract->id));
        $this->assertSame($employee->id, $job->refresh()->assigned_membership_id);
        $this->assertTrue(Activity::query()->where('event', 'employee_access_revoked')->where('subject_id', $employee->id)->exists());
    }

    public function test_revoke_clears_direct_permissions_of_this_firm(): void
    {
        $employee = $this->employeeWithLogin();
        $user = $employee->user;
        $this->assertNotNull($user);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->firm->id);
        $user->givePermissionTo(PermissionEnum::ViewInvoices->value);

        $this->asAdmin()->delete(route('employees.access.destroy', $employee))->assertRedirect();

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->firm->id);
        $this->assertSame(0, $user->unsetRelation('permissions')->permissions()->count());
    }

    public function test_revoke_releases_open_tasks_and_keeps_the_assignee_on_closed_ones(): void
    {
        $employee = $this->employeeWithLogin();
        $open = Task::factory()->assignedTo($employee)->createdBy($this->admin)->create();
        $done = Task::factory()->assignedTo($employee)->createdBy($this->admin)->done()->create();

        $this->asAdmin()->delete(route('employees.access.destroy', $employee))->assertRedirect();

        $this->assertNull(Task::unscopedForSystem()->findOrFail($open->id)->assigned_membership_id);
        $this->assertSame($employee->id, Task::unscopedForSystem()->findOrFail($done->id)->assigned_membership_id);
        $this->assertSame(TaskStatusEnum::Done, Task::unscopedForSystem()->findOrFail($done->id)->status);
    }

    public function test_revoke_keeps_display_name_and_email_for_someone_without_first_and_last_name(): void
    {
        $user = User::factory()->create(['name' => 'Eva Kráľová', 'email' => 'eva@example.com']);
        $this->member($this->firm, 'Interná upratovačka', $user);
        $employee = $this->membershipOf($user, $this->firm);

        $this->asAdmin()->delete(route('employees.access.destroy', $employee))->assertRedirect();

        $employee->refresh();
        $this->assertSame('Eva Kráľová', $employee->display_name);
        $this->assertSame('eva@example.com', $employee->display_email);
    }

    public function test_revoke_closes_every_door_for_the_person(): void
    {
        $employee = $this->employeeWithLogin();
        $user = $employee->user;
        $this->assertNotNull($user);
        DeviceToken::factory()->create(['user_id' => $user->id]);
        $mobile = $user->createToken('mobile', ['mobile'])->plainTextToken;
        $mcp = $this->mcpToken($user);
        $invitation = TenantInvitation::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $this->firm->id,
            'email' => $user->email,
            'role_name' => 'Interná upratovačka',
            'token' => str_repeat('a', 64),
            'status' => InvitationStatusEnum::Pending->value,
            'expires_at' => now()->addDay(),
        ]);

        $this->asAdmin()->delete(route('employees.access.destroy', $employee))->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->withToken($mobile)->withHeaders(['X-Tenant-Id' => $this->firm->id])->getJson('/api/dashboard')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->assertSame([], $this->callTool($mcp, 'list_companies')->rows('companies'));
        $this->assertSame(0, DeviceToken::query()->where('user_id', $user->id)->count());
        $this->assertSame(InvitationStatusEnum::Revoked, $invitation->refresh()->status);
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->post("/invitations/{$invitation->token}", ['name' => 'X', 'password' => 'password123'])->assertStatus(410);
    }

    public function test_the_web_session_loses_the_firm_on_the_next_request(): void
    {
        $employee = $this->employeeWithLogin('Admin');
        $user = $employee->user;
        $this->assertNotNull($user);

        $this->asAdmin()->delete(route('employees.access.destroy', $employee))->assertRedirect();

        $this->consoleContext();
        $this->webAs($user->refresh(), $this->firm)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_revoke_is_refused_for_self_owner_and_actors_without_permission(): void
    {
        $own = $this->membershipOf($this->admin, $this->firm);
        $owner = $this->firm->owner()->firstOrFail();
        $this->member($this->firm, 'Admin', $owner);
        $ownerMembership = $this->membershipOf($owner, $this->firm);
        $target = $this->employeeWithLogin();
        $secretary = $this->member($this->firm, 'Sekretárka');

        $this->asAdmin()->delete(route('employees.access.destroy', $own))->assertForbidden();
        $this->asAdmin()->delete(route('employees.access.destroy', $ownerMembership))->assertForbidden();
        $this->webAs($secretary, $this->firm)->delete(route('employees.access.destroy', $target))->assertForbidden();

        $this->assertNotNull($target->refresh()->user_id);
    }

    public function test_revoke_of_someone_without_a_login_is_forbidden(): void
    {
        $this->asAdmin()->delete(route('employees.access.destroy', $this->employeeWithoutLogin()))->assertForbidden();
    }

    public function test_regrant_after_revoke_with_another_email_and_role_leaves_only_the_new_role_and_a_dead_old_link(): void
    {
        Notification::fake();
        $employee = $this->employeeWithLogin(pending: true);
        $oldEmail = $employee->user?->email;
        $oldUser = $employee->user;
        $this->assertNotNull($oldEmail);
        $this->assertNotNull($oldUser);
        $old = TenantInvitation::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $this->firm->id, 'email' => $oldEmail, 'role_name' => 'Interná upratovačka',
            'token' => str_repeat('b', 64), 'status' => InvitationStatusEnum::Pending->value, 'expires_at' => now()->addDay(),
        ]);

        $this->asAdmin()->delete(route('employees.access.destroy', $employee))->assertRedirect();
        $this->asAdmin()->post(route('employees.access.store', $employee), [
            'email' => 'another@example.com',
            'role_name' => 'Sekretárka',
        ])->assertRedirect();

        $this->assertSame(['Sekretárka'], $this->roleNames(User::where('email', 'another@example.com')->firstOrFail(), $this->firm));
        $this->assertSame([], $this->roleNames($oldUser, $this->firm));
        $this->assertSame(InvitationStatusEnum::Revoked, $old->refresh()->status);
        $this->post('/invitations/'.str_repeat('b', 64), ['name' => 'X', 'password' => 'password123'])->assertStatus(410);
    }

    public function test_resend_replaces_the_previous_token_while_pending(): void
    {
        Notification::fake();
        $employee = $this->employeeWithLogin(pending: true);
        $email = (string) $employee->user?->email;
        $first = TenantInvitation::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $this->firm->id, 'email' => $email, 'role_name' => 'Interná upratovačka',
            'token' => str_repeat('c', 64), 'status' => InvitationStatusEnum::Pending->value, 'expires_at' => now()->subHour(),
        ]);

        $this->asAdmin()->post(route('employees.access.invitation', $employee))
            ->assertRedirect(route('employees.show', $employee))
            ->assertSessionHas('success', __('app.employee_access_invitation_resent'));

        $fresh = $this->pendingInvitation($email);
        $this->assertNotNull($fresh);
        $this->assertNotSame(str_repeat('c', 64), $fresh->token);
        $this->assertSame('Interná upratovačka', $fresh->role_name);
        $this->assertSame(InvitationStatusEnum::Revoked, $first->refresh()->status);
        $this->post('/invitations/'.str_repeat('c', 64), ['name' => 'X', 'password' => 'password123'])->assertStatus(410);
        Notification::assertSentOnDemand(InvitationCreated::class);
        $this->assertTrue(Activity::query()->where('event', 'employee_access_invitation_resent')->exists());
    }

    public function test_resend_is_forbidden_once_the_invitation_was_accepted_or_without_a_login(): void
    {
        $accepted = $this->employeeWithLogin();
        $noLogin = $this->employeeWithoutLogin();

        $this->asAdmin()->post(route('employees.access.invitation', $accepted))->assertForbidden();
        $this->asAdmin()->post(route('employees.access.invitation', $noLogin))->assertForbidden();
    }

    public function test_accepting_does_not_bring_back_a_role_that_was_lowered_while_pending(): void
    {
        Notification::fake();
        $employee = $this->employeeWithoutLogin();
        $this->asAdmin()->post(route('employees.access.store', $employee), ['email' => 'lowered@example.com', 'role_name' => 'Vedúca'])->assertRedirect();
        $token = (string) $this->pendingInvitation('lowered@example.com')?->token;

        $this->asAdmin()->post(route('employees.role', $employee->refresh()), ['role_name' => 'Interná upratovačka'])->assertRedirect();
        $this->consoleContext();
        $this->post("/invitations/{$token}", ['name' => 'Lowered', 'password' => 'Str0ng-Passw0rd!x'])->assertSessionHasNoErrors();

        $user = User::where('email', 'lowered@example.com')->firstOrFail();
        $this->assertSame(['Interná upratovačka'], $this->roleNames($user, $this->firm));
    }

    public function test_deactivating_revokes_a_pending_invitation_and_keeps_the_membership_inactive(): void
    {
        Notification::fake();
        $employee = $this->employeeWithLogin(pending: true);
        $email = (string) $employee->user?->email;
        $invitation = TenantInvitation::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $this->firm->id, 'email' => $email, 'role_name' => 'Interná upratovačka',
            'token' => str_repeat('d', 64), 'status' => InvitationStatusEnum::Pending->value, 'expires_at' => now()->addDay(),
        ]);

        $this->asAdmin()->post(route('employees.deactivate', $employee))->assertRedirect();

        $this->assertSame(InvitationStatusEnum::Revoked, $invitation->refresh()->status);
        $this->post('/invitations/'.str_repeat('d', 64), ['name' => 'X', 'password' => 'password123'])->assertStatus(410);
        $this->assertFalse($employee->refresh()->is_active);
    }

    public function test_deleting_a_user_revokes_a_pending_invitation_so_no_membership_is_recreated(): void
    {
        $employee = $this->employeeWithLogin(pending: true);
        $user = $employee->user;
        $this->assertNotNull($user);
        $invitation = TenantInvitation::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $this->firm->id, 'email' => $user->email, 'role_name' => 'Interná upratovačka',
            'token' => str_repeat('e', 64), 'status' => InvitationStatusEnum::Pending->value, 'expires_at' => now()->addDay(),
        ]);

        $this->asAdmin()->delete(route('users.destroy', $user))->assertRedirect();

        $this->assertSame(InvitationStatusEnum::Revoked, $invitation->refresh()->status);
        $this->post('/invitations/'.str_repeat('e', 64), ['name' => 'X', 'password' => 'password123'])->assertStatus(410);
        $this->assertSame(0, TenantMembership::query()->where('user_id', $user->id)->where('tenant_id', $this->firm->id)->count());
    }

    public function test_the_users_module_create_and_delete_need_manage_employee_access(): void
    {
        $actor = $this->userWithPermission(
            PermissionEnum::ViewEmployees->value,
            PermissionEnum::CreateEmployees->value,
            PermissionEnum::DeleteEmployees->value,
        );
        $tenant = $this->tenantOf($actor);
        $victim = User::factory()->create();
        TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $victim->id]);
        $this->actingAs($actor);

        $this->get(route('users.create'))->assertForbidden();
        $this->post(route('users.store'), ['name' => 'N', 'email' => 'n@example.com', 'password' => 'Str0ng-Passw0rd!x', 'roles' => []])->assertForbidden();
        $this->delete(route('users.destroy', $victim))->assertForbidden();
    }

    public function test_assigning_a_role_to_someone_without_a_login_is_forbidden(): void
    {
        $this->asAdmin()->post(route('employees.role', $this->employeeWithoutLogin()), ['role_name' => 'Interná upratovačka'])->assertForbidden();
    }

    public function test_show_exposes_the_access_flags_and_role_options_for_the_grant_modal(): void
    {
        $noLogin = $this->employeeWithoutLogin();
        $pending = $this->employeeWithLogin(pending: true);

        $this->asAdmin()->get(route('employees.show', $noLogin))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('employee.has_access', false)
            ->where('employee.invitation_pending', false)
            ->where('employee.can.grant_access', true)
            ->where('employee.can.revoke_access', false)
            ->where('employee.can.resend_invitation', false)
            ->where('employee.can.assign_role', false)
            ->where('employee.contact_email', 'kontakt@example.com')
            ->where('employee.user_id', null)
            ->has('roleOptions', 6));

        $this->asAdmin()->get(route('employees.show', $pending))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('employee.has_access', true)
            ->where('employee.invitation_pending', true)
            ->where('employee.can.grant_access', false)
            ->where('employee.can.revoke_access', true)
            ->where('employee.can.resend_invitation', true)
            ->where('employee.can.assign_role', true));
    }

    public function test_role_options_are_sent_when_only_grant_access_is_allowed(): void
    {
        $actor = $this->userWithPermission(PermissionEnum::ViewEmployees->value, PermissionEnum::ManageEmployeeAccess->value);
        $tenant = $this->tenantOf($actor);
        $target = TenantMembership::factory()->withProfile()->withoutUser()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($actor);

        $this->get(route('employees.show', $target))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('employee.can.assign_role', false)
            ->where('employee.can.grant_access', true)
            ->has('roleOptions', 1));
    }

    public function test_create_page_reports_whether_the_actor_may_manage_access(): void
    {
        $this->asAdmin()->get(route('employees.create'))->assertInertia(fn (AssertableInertia $page) => $page->where('context.can_manage_access', true));

        $coordinator = $this->member($this->firm, 'Vedúca');
        $this->webAs($coordinator, $this->firm)->get(route('employees.create'))->assertForbidden();

        $custom = $this->userWithPermission(PermissionEnum::CreateEmployees->value);
        $this->actingAs($custom)->get(route('employees.create'))->assertInertia(fn (AssertableInertia $page) => $page->where('context.can_manage_access', false));
    }

    public function test_the_migration_grants_the_permission_to_admin_only_in_every_firm(): void
    {
        $second = $this->firm('Beta');
        /** @var Migration $migration */
        $migration = require base_path('database/migrations/2026_11_06_000200_add_manage_employee_access_permission.php');
        $registrar = app(PermissionRegistrar::class);

        foreach ([$this->firm, $second] as $tenant) {
            $registrar->setPermissionsTeamId($tenant->id);
            Role::inTenant($tenant->id)->each(fn (Role $role) => $role->revokePermissionTo(PermissionEnum::ManageEmployeeAccess->value));
        }

        $migration->up(); // @phpstan-ignore method.notFound (anonymous migration class)
        $registrar->forgetCachedPermissions();

        foreach ([$this->firm, $second] as $tenant) {
            $registrar->setPermissionsTeamId($tenant->id);
            $holders = Role::inTenant($tenant->id)->get()
                ->filter(fn (Role $role): bool => $role->hasPermissionTo(PermissionEnum::ManageEmployeeAccess->value))
                ->map(fn (Role $role): string => $role->name)
                ->values()
                ->all();
            $this->assertSame(['Admin'], $holders);
        }
    }

    public function test_the_access_routes_are_forbidden_for_an_employee_of_another_firm(): void
    {
        $other = $this->firm('Beta');
        $foreignWithLogin = $this->membershipOf($this->member($other, 'Interná upratovačka', User::factory()->create(['password' => null])), $other);
        $foreignWithoutLogin = TenantMembership::factory()->withoutUser()->create(['tenant_id' => $other->id]);
        $web = $this->asAdmin();

        $web->post(route('employees.access.store', $foreignWithoutLogin), ['email' => 'f@example.com', 'role_name' => 'Interná upratovačka'])->assertForbidden();
        $web->delete(route('employees.access.destroy', $foreignWithLogin))->assertForbidden();
        $web->post(route('employees.access.invitation', $foreignWithLogin))->assertForbidden();

        $this->assertNull($foreignWithoutLogin->refresh()->user_id);
        $this->assertNotNull($foreignWithLogin->refresh()->user_id);
    }

    public function test_show_offers_deactivate_only_when_active_and_reactivate_only_when_inactive(): void
    {
        $active = $this->employeeWithLogin();
        $inactive = TenantMembership::factory()->withProfile()->inactive()->create(['tenant_id' => $this->firm->id]);

        $this->asAdmin()->get(route('employees.show', $active))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('employee.can.deactivate', true)
            ->where('employee.can.reactivate', false));
        $this->asAdmin()->get(route('employees.show', $inactive))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('employee.can.deactivate', false)
            ->where('employee.can.reactivate', true));
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<string, list<string>>
     */
    private function errorsOf(TestResponse $response): array
    {
        $response->assertSessionHasErrors();
        $bag = app('session.store')->get('errors');

        /** @var array<string, list<string>> */
        return $bag instanceof ViewErrorBag ? $bag->getBag('default')->toArray() : [];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, list<string>>
     */
    private function storeErrors(array $overrides): array
    {
        return $this->errorsOf($this->asAdmin()->post(route('employees.store'), $this->storePayload($overrides)));
    }

    public function test_validation_messages_are_readable_without_field_or_flag_names(): void
    {
        app()->setLocale('sk');

        $errors = $this->storeErrors(['employee_type' => null, 'first_name' => null, 'last_name' => null]);
        $this->assertSame([__('app.employee_validation_type_required')], $errors['employee_type']);
        $this->assertSame(['Meno je povinné.'], $errors['first_name']);
        $this->assertSame(['Priezvisko je povinné.'], $errors['last_name']);

        $errors = $this->storeErrors(['has_app_access' => true, 'employee_type' => 'student']);
        $this->assertSame([__('app.employee_validation_type_required')], $errors['employee_type']);
        $this->assertSame([__('app.employee_validation_email_required')], $errors['email']);
        $this->assertSame([__('app.employee_validation_role_required')], $errors['role_name']);

        $errors = $this->storeErrors(['employee_type' => 'self_employed', 'email' => 'nope']);
        $this->assertSame([__('app.employee_validation_ico_required')], $errors['company_ico']);
        $this->assertSame([__('app.employee_validation_email_invalid')], $errors['email']);

        $errors = $this->storeErrors(['has_app_access' => true, 'email' => 'a@example.com', 'role_name' => 'Neexistuje']);
        $this->assertSame([__('app.employee_validation_role_invalid')], $errors['role_name']);
    }

    public function test_the_migration_revokes_pending_invitations_of_deactivated_memberships_only(): void
    {
        $inactiveUser = User::factory()->create(['password' => null]);
        $this->member($this->firm, 'Interná upratovačka', $inactiveUser);
        $this->membershipOf($inactiveUser, $this->firm)->update(['is_active' => false]);
        $activeUser = $this->member($this->firm, 'Interná upratovačka', User::factory()->create(['password' => null]));
        $other = $this->firm('Beta');
        $this->member($other, 'Interná upratovačka', $inactiveUser);

        $make = fn (Tenant $tenant, string $email, string $char) => TenantInvitation::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenant->id, 'email' => $email, 'role_name' => 'Interná upratovačka',
            'token' => str_repeat($char, 64), 'status' => InvitationStatusEnum::Pending->value, 'expires_at' => now()->addDay(),
        ]);
        $stale = $make($this->firm, strtoupper($inactiveUser->email), 'a');
        $live = $make($this->firm, $activeUser->email, 'b');
        $otherFirm = $make($other, $inactiveUser->email, 'c');

        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropColumn(['employee_type', 'contact_email', 'company_name']);
        });
        /** @var Migration $migration */
        $migration = require base_path('database/migrations/2026_11_06_000100_add_employee_access_and_type_to_tenant_memberships.php');
        $migration->up(); // @phpstan-ignore method.notFound (anonymous migration class)

        $this->assertSame(InvitationStatusEnum::Revoked, $stale->refresh()->status);
        $this->assertSame(InvitationStatusEnum::Pending, $live->refresh()->status);
        $this->assertSame(InvitationStatusEnum::Pending, $otherFirm->refresh()->status);
    }
}
