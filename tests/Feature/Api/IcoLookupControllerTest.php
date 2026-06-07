<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class IcoLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('ip:127.0.0.1');
    }

    public function test_returns_lookup_data_for_known_ico(): void
    {
        // Act
        $response = $this->getJson(route('api.icos.lookup', ['ico' => '52119803']));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure(['name', 'dic', 'vat_number', 'address_line', 'city', 'postal_code']);
        $response->assertJsonPath('name', 'CleanPro Bratislava s.r.o.');
    }

    public function test_returns_404_for_unknown_ico(): void
    {
        // Act
        $response = $this->getJson(route('api.icos.lookup', ['ico' => '99999999']));

        // Assert
        $response->assertNotFound();
    }

    public function test_route_returns_404_for_non_numeric_ico(): void
    {
        // Act
        $response = $this->getJson('/api/icos/abc123');

        // Assert
        $response->assertNotFound();
    }

    public function test_rate_limit_returns_429_after_limit(): void
    {
        // Arrange — exhaust the per-IP rate limit (30 per minute)
        for ($i = 0; $i < 30; $i++) {
            $this->getJson(route('api.icos.lookup', ['ico' => '99999999']));
        }

        // Act
        $response = $this->getJson(route('api.icos.lookup', ['ico' => '52119803']));

        // Assert
        $response->assertStatus(429);
    }
}
