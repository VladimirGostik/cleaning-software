<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class TokenAbilityTest extends TestCase
{
    use RefreshDatabase;

    private function tokenUser(): User
    {
        $user = $this->actingAsTenantUser();
        $this->app['auth']->forgetGuards();

        return $user;
    }

    private function mcpToken(User $user): string
    {
        return $user->createToken('mcp', ['mcp'])->plainTextToken;
    }

    public function test_mcp_token_cannot_reach_mobile_routes(): void
    {
        $user = $this->tokenUser();
        $other = User::factory()->create();
        $token = $this->mcpToken($user);

        $this->withToken($token)->getJson('/api/me')->assertForbidden();
        $this->withToken($token)->getJson('/api/dashboard')->assertForbidden();
        $this->withToken($token)->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertForbidden();
        $this->withToken($token)->getJson('/api/users')->assertForbidden();
        $this->withToken($token)->deleteJson("/api/users/{$other->id}")->assertForbidden();

        $this->assertModelExists($other);
    }

    public function test_mcp_token_can_revoke_itself_via_logout(): void
    {
        $user = $this->tokenUser();

        $this->withToken($this->mcpToken($user))->postJson('/api/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_token_is_accepted_on_mobile_routes(): void
    {
        $user = $this->tokenUser();
        $token = $user->createToken('mobile', ['mobile'])->plainTextToken;

        $this->withToken($token)->getJson('/api/me')->assertOk();
    }

    public function test_every_api_route_except_login_and_logout_requires_the_mobile_ability(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'api/'))
            ->reject(fn (LaravelRoute $route): bool => in_array($route->uri(), ['api/auth/login', 'api/auth/logout'], true));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertContains('ability:mobile', $route->gatherMiddleware(), $route->uri());
        }
    }
}
