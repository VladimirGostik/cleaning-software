<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\InvitationStatusEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    // --- Happy paths ---

    public function test_register_page_renders_for_guests(): void
    {
        // Arrange + Act
        $response = $this->get(route('register'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/Register')
            ->has('invitableRoles')
            ->has('colorOptions')
            ->has('languages'),
        );
    }

    public function test_register_page_redirects_authenticated_users(): void
    {
        // Arrange
        $this->actingAsTenantUser('Vlastník');

        // Act
        $response = $this->get(route('register'));

        // Assert
        $response->assertRedirect(route('dashboard'));
    }

    public function test_register_creates_user_tenant_interface_membership_and_roles(): void
    {
        // Arrange
        $payload = $this->validPayload();

        // Act
        $response = $this->post(route('register'), $payload);

        // Assert
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('flash.success');

        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertAuthenticatedAs($user);

        $this->assertNotNull($user->email_verified_at, 'User must be auto-verified on registration (no email-verification flow).');

        // New user defaults to Free plan
        $this->assertSame(SubscriptionPlanEnum::Free, $user->subscription_plan);

        $tenant = $user->tenants()->firstOrFail();

        // Tenant's owner_id = registering user
        $this->assertSame($user->id, $tenant->owner_id);

        $this->assertDatabaseHas('tenant_interfaces', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);

        $roles = Role::where('tenant_id', $tenant->id)->pluck('name');
        $this->assertCount(6, $roles);
        $this->assertContains('Vlastník', $roles);

        $this->assertTrue($user->hasRole('Vlastník'));

        $this->assertSame($tenant->id, session('active_tenant_id'));
    }

    public function test_register_flashes_just_registered_flag(): void
    {
        // Arrange
        $payload = $this->validPayload();

        // Act
        $response = $this->post(route('register'), $payload);

        // Assert
        $response->assertSessionHas('justRegistered', true);
    }

    public function test_register_with_invites_creates_pending_invitation_rows(): void
    {
        // Arrange
        $payload = $this->validPayload([
            'invites' => [
                ['email' => 'veduca@test.sk', 'role_name' => 'Vedúca'],
                ['email' => 'upratovacka@test.sk', 'role_name' => 'Upratovačka'],
                ['email' => 'sekretarka@test.sk', 'role_name' => 'Sekretárka'],
            ],
        ]);

        // Act
        $this->post(route('register'), $payload)->assertRedirect(route('dashboard'));

        // Assert
        $user = User::where('email', $payload['email'])->firstOrFail();
        $tenant = $user->tenants()->firstOrFail();

        $invitations = TenantInvitation::where('tenant_id', $tenant->id)->get();
        $this->assertCount(3, $invitations);

        foreach ($invitations as $invitation) {
            $this->assertSame(InvitationStatusEnum::Pending, $invitation->status);
            $this->assertNotEmpty($invitation->token);
            $this->assertNotNull($invitation->expires_at);
            $this->assertTrue($invitation->expires_at->isFuture());
        }

        $tokens = $invitations->pluck('token')->unique();
        $this->assertCount(3, $tokens);
    }

    // --- Failure paths ---

    public function test_register_fails_with_duplicate_email(): void
    {
        // Arrange
        User::factory()->create(['email' => 'existing@test.sk']);
        $payload = $this->validPayload(['email' => 'existing@test.sk']);

        // Act
        $response = $this->post(route('register'), $payload);

        // Assert
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('tenant_interfaces', 0);
    }

    public function test_register_fails_when_terms_not_accepted(): void
    {
        // Arrange
        $payload = $this->validPayload(['terms_accepted' => false]);

        // Act
        $response = $this->post(route('register'), $payload);

        // Assert
        $response->assertSessionHasErrors('terms_accepted');
    }

    public function test_register_fails_when_vat_payer_without_vat_number(): void
    {
        // Arrange
        $payload = $this->validPayload([
            'company' => [
                'name' => 'Test s.r.o.',
                'ico' => '87654321',
                'is_vat_payer' => true,
                'vat_number' => '',
                'address_line' => 'Testovacia 1',
                'city' => 'Bratislava',
                'postal_code' => '811 01',
                'country' => 'SK',
            ],
        ]);

        // Act
        $response = $this->post(route('register'), $payload);

        // Assert
        $response->assertSessionHasErrors('company.vat_number');
    }

    public function test_register_fails_with_weak_password(): void
    {
        // Arrange
        $payload = $this->validPayload(['password' => 'weak', 'password_confirmation' => 'weak']);

        // Act
        $response = $this->post(route('register'), $payload);

        // Assert
        $response->assertSessionHasErrors('password');
    }

    public function test_register_fails_when_invite_role_is_vlastnik(): void
    {
        // Arrange
        $payload = $this->validPayload([
            'invites' => [
                ['email' => 'someone@test.sk', 'role_name' => 'Vlastník'],
            ],
        ]);

        // Act
        $response = $this->post(route('register'), $payload);

        // Assert
        $response->assertSessionHasErrors('invites.0.role_name');
    }

    // --- Edge cases ---

    public function test_register_skips_invite_with_same_email_as_registrant(): void
    {
        // Arrange
        $email = 'owner@test.sk';
        $payload = $this->validPayload([
            'email' => $email,
            'invites' => [
                ['email' => $email, 'role_name' => 'Vedúca'],
            ],
        ]);

        // Act
        $this->post(route('register'), $payload)->assertRedirect(route('dashboard'));

        // Assert — no invitation for the owner's own email
        $this->assertDatabaseCount('tenant_invitations', 0);
    }

    public function test_register_deduplicates_invite_emails_via_firstorcreate(): void
    {
        // Arrange
        $payload = $this->validPayload([
            'invites' => [
                ['email' => 'same@test.sk', 'role_name' => 'Vedúca'],
                ['email' => 'same@test.sk', 'role_name' => 'Sekretárka'],
            ],
        ]);

        // Act
        $this->post(route('register'), $payload)->assertRedirect(route('dashboard'));

        // Assert
        $this->assertDatabaseCount('tenant_invitations', 1);
    }

    public function test_register_rollback_on_mid_transaction_failure(): void
    {
        // Arrange — force uniqueness violation on user creation by pre-creating same email
        $email = 'conflict@test.sk';
        User::factory()->create(['email' => $email]);

        $payload = $this->validPayload(['email' => $email]);

        // Act
        $this->post(route('register'), $payload);

        // Assert — no orphan tenant or interface
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('tenant_interfaces', 0);
        $this->assertDatabaseCount('tenant_memberships', 0);
    }

    // --- Helper ---

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Ján Novák',
            'email' => 'jan.novak@test.sk',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'terms_accepted' => true,
            'company' => [
                'name' => 'Test Cleaning s.r.o.',
                'ico' => '11223344',
                'dic' => null,
                'vat_number' => null,
                'is_vat_payer' => false,
                'address_line' => 'Testovacia 1',
                'city' => 'Bratislava',
                'postal_code' => '811 01',
                'country' => 'SK',
            ],
            'invites' => [],
        ], $overrides);
    }
}
