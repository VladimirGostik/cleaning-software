<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_same_overview_as_the_service(): void
    {
        $user = $this->actingAsTenantUser();
        $this->app['auth']->forgetGuards();
        $token = $user->createToken('mobile', ['mobile'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/dashboard');

        $response->assertOk();
        $response->assertJsonStructure(['companies', 'alerts', 'alert_counts', 'generated_at']);
        $expected = app(DashboardService::class)->overviewFor($user)->toArray();
        $this->assertSame($expected['companies'], $response->json('companies'));
        $this->assertSame($expected['alerts'], $response->json('alerts'));
        $this->assertSame($expected['alert_counts'], $response->json('alert_counts'));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
