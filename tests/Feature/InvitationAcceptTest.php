<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvitationStatusEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class InvitationAcceptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTenantWithInvitation(string $email, string $roleName = 'Upratovačka'): array
    {
        $owner = User::factory()->create(['is_active' => true]);
        $tenant = Tenant::factory()->forOwner($owner)->create();

        TenantMembership::create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->seed(RoleTemplatesSeeder::class);

        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'invited_by_user_id' => $owner->id,
            'email' => $email,
            'role_name' => $roleName,
        ]);

        return [$tenant, $invitation, $owner];
    }

    // -------------------------------------------------------------------------
    // Accept as NEW user — happy path
    // -------------------------------------------------------------------------

    public function test_new_user_can_accept_invitation(): void
    {
        // Arrange
        [$tenant, $invitation] = $this->makeTenantWithInvitation('newuser@example.com');

        // Act
        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'name' => 'Nový Používateľ',
            'password' => 'password',
        ]);

        // Assert
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('flash.success');

        $user = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertSame('Nový Používateľ', $user->name);
        $this->assertSame(SubscriptionPlanEnum::Free, $user->subscription_plan);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->ownedTenants()->first()); // Q3 — no owned tenant

        $this->assertDatabaseHas('tenant_memberships', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $invitation->refresh();
        $this->assertSame(InvitationStatusEnum::Accepted, $invitation->status);
        $this->assertNotNull($invitation->accepted_at);

        $this->assertAuthenticatedAs($user);
        $this->assertSame($tenant->id, session('active_tenant_id'));
    }

    // -------------------------------------------------------------------------
    // Accept as NEW user — failure: missing name
    // -------------------------------------------------------------------------

    public function test_new_user_accept_fails_without_name(): void
    {
        // Arrange
        [, $invitation] = $this->makeTenantWithInvitation('noname@example.com');

        // Act
        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'password' => 'password',
        ]);

        // Assert
        $response->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('users', ['email' => 'noname@example.com']);

        $invitation->refresh();
        $this->assertSame(InvitationStatusEnum::Pending, $invitation->status);
    }

    // -------------------------------------------------------------------------
    // Accept as EXISTING user — happy path
    // -------------------------------------------------------------------------

    public function test_existing_user_can_accept_invitation(): void
    {
        // Arrange
        [$tenant, $invitation] = $this->makeTenantWithInvitation('existing@example.com');

        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('correct-password'),
            'is_active' => true,
        ]);

        // Act
        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'password' => 'correct-password',
        ]);

        // Assert
        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('tenant_memberships', [
            'user_id' => $existingUser->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $invitation->refresh();
        $this->assertSame(InvitationStatusEnum::Accepted, $invitation->status);
        $this->assertNotNull($invitation->accepted_at);

        $this->assertAuthenticatedAs($existingUser);
    }

    // -------------------------------------------------------------------------
    // Accept as EXISTING user — failure: wrong password
    // -------------------------------------------------------------------------

    public function test_existing_user_accept_fails_with_wrong_password(): void
    {
        // Arrange
        [$tenant, $invitation] = $this->makeTenantWithInvitation('existing2@example.com');

        $invitedUser = User::factory()->create([
            'email' => 'existing2@example.com',
            'password' => Hash::make('correct-password'),
            'is_active' => true,
        ]);

        // Act
        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'password' => 'wrong-password',
        ]);

        // Assert
        $response->assertSessionHasErrors('password');

        // Invited user must NOT have a membership on this tenant
        $this->assertDatabaseMissing('tenant_memberships', [
            'user_id' => $invitedUser->id,
            'tenant_id' => $tenant->id,
        ]);

        $invitation->refresh();
        $this->assertSame(InvitationStatusEnum::Pending, $invitation->status);
        $this->assertGuest();
    }

    // -------------------------------------------------------------------------
    // Forbidden states — expired invitation
    // -------------------------------------------------------------------------

    public function test_expired_invitation_get_shows_expired_state(): void
    {
        // Arrange
        [, $invitation] = $this->makeTenantWithInvitation('expired@example.com');
        $invitation->forceFill(['expires_at' => now()->subDay()])->save();

        // Act
        $response = $this->get(route('invitations.show', ['token' => $invitation->token]));

        // Assert
        $response->assertInertia(fn ($page) => $page->where('state', 'expired'));
    }

    public function test_expired_invitation_post_returns_410(): void
    {
        // Arrange
        [, $invitation] = $this->makeTenantWithInvitation('expired2@example.com');
        $invitation->forceFill(['expires_at' => now()->subDay()])->save();

        // Act
        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'name' => 'Test',
            'password' => 'password',
        ]);

        // Assert
        $response->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => 'expired2@example.com']);
    }

    // -------------------------------------------------------------------------
    // Forbidden states — non-pending invitation (already accepted)
    // -------------------------------------------------------------------------

    public function test_already_accepted_invitation_post_returns_410(): void
    {
        // Arrange
        [, $invitation] = $this->makeTenantWithInvitation('accepted@example.com');
        $invitation->forceFill([
            'status' => InvitationStatusEnum::Accepted,
            'accepted_at' => now(),
        ])->save();

        // Act
        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'name' => 'Test',
            'password' => 'password',
        ]);

        // Assert
        $response->assertStatus(410);
    }

    // -------------------------------------------------------------------------
    // Forbidden states — token not found
    // -------------------------------------------------------------------------

    public function test_unknown_token_returns_404(): void
    {
        // Act
        $response = $this->get(route('invitations.show', ['token' => str_repeat('a', 64)]));

        // Assert
        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Logged-in edge — same-email user auto-accepts on GET
    // -------------------------------------------------------------------------

    public function test_same_email_logged_in_user_auto_accepts_on_get(): void
    {
        // Arrange
        [$tenant, $invitation] = $this->makeTenantWithInvitation('sameuser@example.com');

        $user = User::factory()->create([
            'email' => 'sameuser@example.com',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        // Act
        $response = $this->get(route('invitations.show', ['token' => $invitation->token]));

        // Assert
        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('tenant_memberships', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $invitation->refresh();
        $this->assertSame(InvitationStatusEnum::Accepted, $invitation->status);
    }

    // -------------------------------------------------------------------------
    // Logged-in edge — different user sees wrong_user state
    // -------------------------------------------------------------------------

    public function test_different_logged_in_user_sees_wrong_user_state(): void
    {
        // Arrange
        [$tenant, $invitation] = $this->makeTenantWithInvitation('invited@example.com');

        $otherUser = User::factory()->create([
            'email' => 'other@example.com',
            'is_active' => true,
        ]);
        $this->actingAs($otherUser);

        // Act
        $response = $this->get(route('invitations.show', ['token' => $invitation->token]));

        // Assert
        $response->assertInertia(fn ($page) => $page
            ->where('state', 'wrong_user')
            ->where('invitedEmail', 'invited@example.com'),
        );

        $this->assertDatabaseMissing('tenant_memberships', [
            'user_id' => $otherUser->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Idempotency — inactive membership gets reactivated
    // -------------------------------------------------------------------------

    public function test_inactive_membership_is_reactivated_on_accept(): void
    {
        // Arrange
        [$tenant, $invitation] = $this->makeTenantWithInvitation('inactive@example.com');

        $existingUser = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        TenantMembership::create([
            'user_id' => $existingUser->id,
            'tenant_id' => $tenant->id,
            'is_active' => false,
            'joined_at' => now()->subMonth(),
        ]);

        // Act
        $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        // Assert — reactivated, no duplicate
        $this->assertDatabaseHas('tenant_memberships', [
            'user_id' => $existingUser->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $this->assertSame(
            1,
            TenantMembership::where('user_id', $existingUser->id)->where('tenant_id', $tenant->id)->count(),
        );
    }

    // -------------------------------------------------------------------------
    // Idempotency — double-accept same token returns 410
    // -------------------------------------------------------------------------

    public function test_double_accept_returns_410(): void
    {
        // Arrange
        [, $invitation] = $this->makeTenantWithInvitation('double@example.com');

        // First accept
        $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'name' => 'First Accept',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->app->get('auth')->logout();

        // Act — second accept attempt
        $response = $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'name' => 'Second Accept',
            'password' => 'password',
        ]);

        // Assert
        $response->assertStatus(410);
    }

    // -------------------------------------------------------------------------
    // Multi-tenant — role assigned under invitation tenant_id, not session tenant
    // -------------------------------------------------------------------------

    public function test_role_assigned_under_invitation_tenant_not_session_tenant(): void
    {
        // Arrange — two separate tenants; user has session on a different tenant
        [$invitingTenant, $invitation] = $this->makeTenantWithInvitation('multitenant@example.com', 'Upratovačka');

        $sessionUser = $this->actingAsTenantUser('Vlastník');
        $sessionTenantId = session('active_tenant_id');

        $this->assertNotSame($invitingTenant->id, $sessionTenantId);

        $invitedUser = User::factory()->create([
            'email' => 'multitenant@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->actingAs($invitedUser);

        // Act
        $this->post(route('invitations.accept', ['token' => $invitation->token]), [
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        // Assert — role on inviting tenant, not session tenant
        app(PermissionRegistrar::class)->setPermissionsTeamId($invitingTenant->id);

        $this->assertTrue(
            $invitedUser->fresh()->hasRole('Upratovačka'),
            'Role should be assigned on the inviting tenant scope',
        );

        $this->assertSame($invitingTenant->id, session('active_tenant_id'));
    }
}
