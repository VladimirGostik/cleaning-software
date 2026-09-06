<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\InvitationAcceptStateEnum;
use App\Enums\InvitationStatusEnum;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class InvitationAcceptTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithRole(string $roleName = 'Vedúca'): Tenant
    {
        $tenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($tenant);

        return $tenant;
    }

    private function invitation(Tenant $tenant, array $overrides = []): TenantInvitation
    {
        $this->bindTenant($tenant);

        return TenantInvitation::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'role_name' => 'Vedúca',
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('invitation-accept|127.0.0.1');
    }

    // ── show — 4 states ──────────────────────────────────────────────────────

    public function test_show_expired_invitation(): void
    {
        $tenant = $this->tenantWithRole();
        $invitation = $this->invitation($tenant, ['status' => InvitationStatusEnum::Expired->value, 'expires_at' => now()->subDay()]);

        $response = $this->withoutVite()->get("/invitations/{$invitation->token}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invitations/Accept')
            ->where('invitation.state', InvitationAcceptStateEnum::Expired->value),
        );
    }

    public function test_show_wrong_user_when_logged_in_as_different_email(): void
    {
        $tenant = $this->tenantWithRole();
        $invitation = $this->invitation($tenant, ['email' => 'invited@example.com']);
        $otherUser = User::factory()->create(['email' => 'someoneelse@example.com']);

        $response = $this->withoutVite()->actingAs($otherUser)->get("/invitations/{$invitation->token}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invitations/Accept')
            ->where('invitation.state', InvitationAcceptStateEnum::WrongUser->value)
            ->where('invitation.invited_email', 'invited@example.com'),
        );
    }

    public function test_show_existing_user_state(): void
    {
        $tenant = $this->tenantWithRole();
        User::factory()->create(['email' => 'existing@example.com']);
        $invitation = $this->invitation($tenant, ['email' => 'existing@example.com']);

        $response = $this->withoutVite()->get("/invitations/{$invitation->token}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invitations/Accept')
            ->where('invitation.state', InvitationAcceptStateEnum::ExistingUser->value),
        );
    }

    public function test_show_new_user_state(): void
    {
        $tenant = $this->tenantWithRole();
        $invitation = $this->invitation($tenant, ['email' => 'brandnew@example.com']);

        $response = $this->withoutVite()->get("/invitations/{$invitation->token}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invitations/Accept')
            ->where('invitation.state', InvitationAcceptStateEnum::NewUser->value)
            ->where('invitation.tenant_name', $tenant->name)
            ->where('invitation.role_name', 'Vedúca'),
        );
    }

    public function test_show_unknown_token_returns_404(): void
    {
        $response = $this->get('/invitations/'.str_repeat('a', 64));

        $response->assertNotFound();
    }

    public function test_show_logged_in_same_email_auto_accepts(): void
    {
        $tenant = $this->tenantWithRole();
        $user = User::factory()->create(['email' => 'same@example.com', 'password' => Hash::make('whatever-password')]);
        $invitation = $this->invitation($tenant, ['email' => 'same@example.com']);

        $response = $this->actingAs($user)->get("/invitations/{$invitation->token}");

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($tenant->id, session('active_tenant_id'));
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);
    }

    // ── accept — new user ────────────────────────────────────────────────────

    public function test_accept_new_user_happy_path(): void
    {
        $tenant = $this->tenantWithRole();
        $invitation = $this->invitation($tenant, ['email' => 'newperson@example.com']);

        $response = $this->post("/invitations/{$invitation->token}", [
            'name' => 'New Person',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertSame($tenant->id, session('active_tenant_id'));

        $user = User::where('email', 'newperson@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->assertTrue($user->hasRole('Vedúca'));

        $this->assertSame(InvitationStatusEnum::Accepted, $invitation->fresh()->status);
    }

    public function test_accept_new_user_missing_name_returns_422(): void
    {
        $tenant = $this->tenantWithRole();
        $invitation = $this->invitation($tenant, ['email' => 'noname@example.com']);

        $response = $this->post("/invitations/{$invitation->token}", [
            'password' => 'password123',
        ]);

        $response->assertInvalid(['name']);
    }

    public function test_accept_new_user_with_short_password_returns_422(): void
    {
        $tenant = $this->tenantWithRole();
        $invitation = $this->invitation($tenant, ['email' => 'shortpw@example.com']);

        $response = $this->post("/invitations/{$invitation->token}", [
            'name' => 'Short Password',
            'password' => 'short12',
        ]);

        $response->assertInvalid(['password']);
        $this->assertDatabaseMissing('users', ['email' => 'shortpw@example.com']);
    }

    // ── accept — existing user ───────────────────────────────────────────────

    public function test_accept_existing_user_happy_path(): void
    {
        $tenant = $this->tenantWithRole();
        $user = User::factory()->create(['email' => 'existing@example.com', 'password' => Hash::make('current-password')]);
        $invitation = $this->invitation($tenant, ['email' => 'existing@example.com']);

        $response = $this->post("/invitations/{$invitation->token}", [
            'password' => 'current-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);
    }

    public function test_accept_existing_user_short_password_is_not_rejected_by_strength_rule(): void
    {
        $tenant = $this->tenantWithRole();
        User::factory()->create(['email' => 'shortlogin@example.com', 'password' => Hash::make('abc123x')]);
        $invitation = $this->invitation($tenant, ['email' => 'shortlogin@example.com']);

        $response = $this->post("/invitations/{$invitation->token}", [
            'password' => 'abc123x',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_accept_existing_user_wrong_password_returns_422(): void
    {
        $tenant = $this->tenantWithRole();
        User::factory()->create(['email' => 'existing2@example.com', 'password' => Hash::make('right-password')]);
        $invitation = $this->invitation($tenant, ['email' => 'existing2@example.com']);

        $response = $this->post("/invitations/{$invitation->token}", [
            'password' => 'wrong-password',
        ]);

        $response->assertInvalid(['password']);
    }

    public function test_accept_reactivates_inactive_membership(): void
    {
        $tenant = $this->tenantWithRole();
        $user = User::factory()->create(['email' => 'rejoin@example.com', 'password' => Hash::make('password123')]);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => false, 'joined_at' => now()->subYear()]);
        $invitation = $this->invitation($tenant, ['email' => 'rejoin@example.com']);

        $this->post("/invitations/{$invitation->token}", ['password' => 'password123']);

        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);
    }

    // ── accept — invalid states ──────────────────────────────────────────────

    public function test_accept_expired_invitation_returns_410(): void
    {
        $tenant = $this->tenantWithRole();
        $invitation = $this->invitation($tenant, ['email' => 'expired@example.com', 'status' => InvitationStatusEnum::Expired->value, 'expires_at' => now()->subDay()]);

        $response = $this->post("/invitations/{$invitation->token}", ['name' => 'X', 'password' => 'password123']);

        $response->assertStatus(410);
    }

    public function test_accept_already_accepted_invitation_returns_410(): void
    {
        $tenant = $this->tenantWithRole();
        $invitation = $this->invitation($tenant, ['email' => 'done@example.com', 'status' => InvitationStatusEnum::Accepted->value, 'accepted_at' => now()]);

        $response = $this->post("/invitations/{$invitation->token}", ['name' => 'X', 'password' => 'password123']);

        $response->assertStatus(410);
    }

    public function test_accept_unknown_token_returns_404(): void
    {
        $response = $this->post('/invitations/'.str_repeat('b', 64), ['name' => 'X', 'password' => 'password123']);

        $response->assertNotFound();
    }

    public function test_accept_role_assigned_under_invitation_tenant_not_session_tenant(): void
    {
        $sessionTenant = $this->tenantWithRole();
        $invitationTenant = $this->tenantWithRole();
        $invitation = $this->invitation($invitationTenant, ['email' => 'crosstenant@example.com']);

        // Simulate a logged-out guest whose browser happens to carry an unrelated session tenant.
        $this->withSession(['active_tenant_id' => $sessionTenant->id])
            ->post("/invitations/{$invitation->token}", ['name' => 'Cross Tenant', 'password' => 'password123']);

        $this->assertSame($invitationTenant->id, session('active_tenant_id'));

        $user = User::where('email', 'crosstenant@example.com')->firstOrFail();
        app(PermissionRegistrar::class)->setPermissionsTeamId($invitationTenant->id);
        $this->assertTrue($user->hasRole('Vedúca'));
        app(PermissionRegistrar::class)->setPermissionsTeamId($sessionTenant->id);
        $this->assertFalse($user->fresh()->hasRole('Vedúca'));
    }

    public function test_accept_is_rate_limited_after_five_attempts_per_minute(): void
    {
        $tenant = $this->tenantWithRole();
        $invitation = $this->invitation($tenant, ['email' => 'ratelimited@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post("/invitations/{$invitation->token}", ['password' => 'wrong']);
        }

        $response = $this->post("/invitations/{$invitation->token}", ['password' => 'wrong']);

        $response->assertStatus(429);
    }
}
