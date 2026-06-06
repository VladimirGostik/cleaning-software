<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('email:throttle@example.com');
    }

    public function test_login_rate_limiter_is_registered(): void
    {
        $this->assertTrue(
            RateLimiter::limiter('login') !== null,
            'login rate limiter must be registered in AppServiceProvider',
        );
    }

    public function test_password_reset_rate_limiter_is_registered(): void
    {
        $this->assertTrue(
            RateLimiter::limiter('password-reset') !== null,
            'password-reset rate limiter must be registered in AppServiceProvider',
        );
    }

    public function test_password_reset_confirm_rate_limiter_is_registered(): void
    {
        $this->assertTrue(
            RateLimiter::limiter('password-reset-confirm') !== null,
            'password-reset-confirm rate limiter must be registered in AppServiceProvider',
        );
    }

    public function test_post_login_returns_429_after_per_email_limit_exceeded(): void
    {
        // Arrange — exhaust the per-email bucket (5 per minute) for the throttle key
        $email = 'throttle@example.com';
        User::factory()->create(['email' => $email, 'password' => bcrypt('password')]);

        // Hit the route 5 times with wrong password to consume the per-email limit
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => $email,
                'password' => 'wrong-password',
            ]);
        }

        // Act — 6th attempt must be rate-limited
        $response = $this->post(route('login'), [
            'email' => $email,
            'password' => 'wrong-password',
        ]);

        // Assert
        $response->assertStatus(429);
    }

    public function test_post_forgot_password_route_has_throttle_middleware(): void
    {
        // Collect route middleware for POST /forgot-password
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'password.email');

        $this->assertNotNull($route, 'password.email route must exist');

        $middlewares = $route->gatherMiddleware();

        $hasThrottle = collect($middlewares)->contains(
            fn ($m) => str_starts_with((string) $m, 'throttle:password-reset'),
        );

        $this->assertTrue($hasThrottle, 'POST /forgot-password must carry throttle:password-reset middleware');
    }

    public function test_post_reset_password_route_has_throttle_middleware(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'password.store');

        $this->assertNotNull($route, 'password.store route must exist');

        $middlewares = $route->gatherMiddleware();

        $hasThrottle = collect($middlewares)->contains(
            fn ($m) => str_starts_with((string) $m, 'throttle:password-reset-confirm'),
        );

        $this->assertTrue($hasThrottle, 'POST /reset-password must carry throttle:password-reset-confirm middleware');
    }
}
