<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class UserAutocompleteTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function addMember(Tenant $tenant, User $user, bool $active = true): void
    {
        TenantMembership::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_active' => $active,
            'joined_at' => now(),
        ]);
    }

    public function test_autocomplete_returns_matching_active_users_by_name(): void
    {
        $admin = $this->userWithPermission('view employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $alice = User::factory()->create(['name' => 'Alice Wonderland', 'email' => 'alice@example.test']);
        $this->addMember($tenant, $alice);
        $bob = User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.test']);
        $this->addMember($tenant, $bob);

        $response = $this->actingAs($admin)->getJson('/users/autocomplete?q=alic');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $alice->id);
        $response->assertJsonPath('0.name', 'Alice Wonderland');
        $response->assertJsonPath('0.email', 'alice@example.test');
    }

    public function test_autocomplete_returns_matching_active_users_by_email(): void
    {
        $admin = $this->userWithPermission('view employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $target = User::factory()->create(['name' => 'Random Person', 'email' => 'specific.user@company.io']);
        $this->addMember($tenant, $target);
        $other = User::factory()->create(['name' => 'Other', 'email' => 'other@example.test']);
        $this->addMember($tenant, $other);

        $response = $this->actingAs($admin)->getJson('/users/autocomplete?q=specific');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $target->id);
    }

    public function test_autocomplete_excludes_inactive_users(): void
    {
        // Autocomplete scopes by membership status in the active tenant, not the
        // global `users.is_active` flag — a deactivated membership must exclude
        // the user even though their global account flag stays true.
        $admin = $this->userWithPermission('view employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $active = User::factory()->create(['name' => 'Active Charlie']);
        $this->addMember($tenant, $active);
        $inactiveMember = User::factory()->create(['name' => 'Inactive Charlie']);
        $this->addMember($tenant, $inactiveMember, active: false);

        $response = $this->actingAs($admin)->getJson('/users/autocomplete?q=Charlie');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', 'Active Charlie');
    }

    public function test_autocomplete_excludes_users_who_are_members_of_another_tenant_only(): void
    {
        $admin = $this->userWithPermission('view employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $foreignTenant = Tenant::factory()->create();
        $outsider = User::factory()->create(['name' => 'Outsider Erin']);
        $this->addMember($foreignTenant, $outsider);
        $member = User::factory()->create(['name' => 'Member Erin']);
        $this->addMember($tenant, $member);

        $response = $this->actingAs($admin)->getJson('/users/autocomplete?q=Erin');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', 'Member Erin');
    }

    public function test_autocomplete_returns_empty_for_query_shorter_than_two_chars(): void
    {
        $admin = $this->userWithPermission('view employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $this->addMember($tenant, User::factory()->create(['name' => 'Anybody']));

        $response = $this->actingAs($admin)->getJson('/users/autocomplete?q=a');

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_autocomplete_returns_initial_active_users_when_no_query(): void
    {
        $admin = $this->userWithPermission('view employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        User::factory()->count(5)->create()->each(fn (User $u) => $this->addMember($tenant, $u));
        $this->addMember($tenant, User::factory()->create(), active: false);

        $response = $this->actingAs($admin)->getJson('/users/autocomplete');

        $response->assertOk();
        // 5 active + the admin's own membership = 6
        $response->assertJsonCount(6);
        $response->assertJsonStructure([['id', 'name', 'email']]);
    }

    public function test_autocomplete_treats_whitespace_only_query_as_empty(): void
    {
        $admin = $this->userWithPermission('view employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        User::factory()->count(5)->create()->each(fn (User $u) => $this->addMember($tenant, $u));
        $this->addMember($tenant, User::factory()->create(), active: false);

        $response = $this->actingAs($admin)->getJson('/users/autocomplete?q=%20%20');

        $response->assertOk();
        $response->assertJsonCount(6);
    }

    public function test_autocomplete_limits_to_twenty_results(): void
    {
        $admin = $this->userWithPermission('view employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));

        for ($i = 0; $i < 25; $i++) {
            $this->addMember($tenant, User::factory()->create(['name' => 'Searchable User '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]));
        }

        $response = $this->actingAs($admin)->getJson('/users/autocomplete?q=Searchable');

        $response->assertOk();
        $response->assertJsonCount(20);
    }

    public function test_autocomplete_sorts_results_by_name_asc(): void
    {
        $admin = $this->userWithPermission('view employees');
        $tenant = Tenant::find((string) app('current_tenant_id'));
        $this->addMember($tenant, User::factory()->create(['name' => 'Zeta Sorted']));
        $this->addMember($tenant, User::factory()->create(['name' => 'Alpha Sorted']));
        $this->addMember($tenant, User::factory()->create(['name' => 'Mike Sorted']));

        $response = $this->actingAs($admin)->getJson('/users/autocomplete?q=Sorted');

        $response->assertOk();
        $response->assertJsonPath('0.name', 'Alpha Sorted');
        $response->assertJsonPath('1.name', 'Mike Sorted');
        $response->assertJsonPath('2.name', 'Zeta Sorted');
    }

    public function test_autocomplete_requires_view_users_permission(): void
    {
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)->getJson('/users/autocomplete?q=ab');

        $response->assertForbidden();
    }

    public function test_autocomplete_redirects_guest_to_login(): void
    {
        $response = $this->get('/users/autocomplete?q=ab');

        $response->assertRedirect('/login');
    }

    public function test_autocomplete_does_not_collide_with_user_edit_route(): void
    {
        $admin = $this->userWithPermission('view employees');

        $response = $this->actingAs($admin)->getJson('/users/autocomplete?q=xy');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
    }
}
