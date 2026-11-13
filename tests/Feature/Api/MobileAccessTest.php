<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\PermissionEnum;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

final class MobileAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Tenant, Tenant} */
    private function userWithMobileOnlyInFirstTenant(): array
    {
        $user = User::factory()->create(['email' => 'lead@example.com']);
        $tenantA = Tenant::factory()->forOwner($user)->create(['name' => 'A firm']);
        $tenantB = Tenant::factory()->create(['name' => 'B firm']);

        $this->actingAsTenantUser('Vedúca', $tenantA, $user);
        $this->actingAsTenantUser('Sekretárka', $tenantB, $user);
        $this->app['auth']->forgetGuards();

        return [$user, $tenantA, $tenantB];
    }

    public function test_login_issues_a_mobile_only_token_when_permission_held_in_one_tenant(): void
    {
        $this->userWithMobileOnlyInFirstTenant();

        $response = $this->postJson('/api/auth/login', ['email' => 'lead@example.com', 'password' => 'password']);

        $response->assertOk();
        $token = PersonalAccessToken::query()->sole();
        $this->assertSame(['mobile'], $token->abilities);
        $this->assertSame('mobile', $token->name);
    }

    public function test_me_lists_companies_with_per_tenant_mobile_access(): void
    {
        [$user, $tenantA, $tenantB] = $this->userWithMobileOnlyInFirstTenant();
        $plain = $user->createToken('mobile', ['mobile'])->plainTextToken;

        $response = $this->withToken($plain)->withHeaders(['X-Tenant-Id' => $tenantA->id])->getJson('/api/me');

        $response->assertOk();
        $response->assertJsonPath('activeTenantId', $tenantA->id);
        $response->assertJsonPath('email', 'lead@example.com');
        /** @var list<array{tenant_id: string, mobile_access: bool, permissions: list<string>}> $rows */
        $rows = $response->json('companies');
        $companies = array_column($rows, null, 'tenant_id');
        $this->assertTrue($companies[$tenantA->id]['mobile_access']);
        $this->assertFalse($companies[$tenantB->id]['mobile_access']);
        $this->assertContains(PermissionEnum::AccessMobileApp->value, $companies[$tenantA->id]['permissions']);
        $this->assertNotContains(PermissionEnum::AccessMobileApp->value, $companies[$tenantB->id]['permissions']);
    }

    public function test_tenant_without_the_permission_is_forbidden_for_mobile_tokens(): void
    {
        [$user, , $tenantB] = $this->userWithMobileOnlyInFirstTenant();
        $plain = $user->createToken('mobile', ['mobile'])->plainTextToken;

        $response = $this->withToken($plain)->withHeaders(['X-Tenant-Id' => $tenantB->id])->getJson('/api/me');

        $response->assertForbidden();
        $response->assertJsonPath('message', __('app.mobile_forbidden'));
    }

    public function test_web_session_is_unaffected_for_tenant_without_the_permission(): void
    {
        [$user, , $tenantB] = $this->userWithMobileOnlyInFirstTenant();

        $this->actingAs($user)->withSession(['active_tenant_id' => $tenantB->id]);

        $this->get('/profile')->assertSuccessful();
    }

    public function test_login_without_the_permission_anywhere_is_rejected_and_creates_no_token(): void
    {
        $user = User::factory()->create(['email' => 'cleaner@example.com']);
        $this->actingAsTenantUser('Sekretárka', null, $user);

        $response = $this->postJson('/api/auth/login', ['email' => 'cleaner@example.com', 'password' => 'password']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
        $response->assertJsonPath('errors.email.0', __('app.mobile_forbidden'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_and_leader_templates_carry_the_mobile_permission(): void
    {
        $this->assertContains(PermissionEnum::AccessMobileApp->value, RoleTemplatesSeeder::templates()['Vedúca']);
        $this->assertNotContains(PermissionEnum::AccessMobileApp->value, RoleTemplatesSeeder::templates()['Sekretárka']);
    }

    public function test_headerless_mobile_request_resolves_first_tenant_with_mobile_access(): void
    {
        $user = User::factory()->create(['email' => 'late@example.com']);
        $tenantA = Tenant::factory()->forOwner($user)->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenantA, $user);
        $this->actingAsTenantUser('Vedúca', $tenantB, $user);
        TenantMembership::query()->where('tenant_id', $tenantA->id)->update(['joined_at' => now()->subDay()]);
        $this->app['auth']->forgetGuards();
        $plain = $user->createToken('mobile', ['mobile'])->plainTextToken;

        $this->withToken($plain)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('activeTenantId', $tenantB->id);

        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->withHeaders(['X-Tenant-Id' => $tenantA->id])->getJson('/api/me')
            ->assertForbidden();
    }
}
