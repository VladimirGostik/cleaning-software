<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class ExampleTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $user = $this->adminUser();

        $response = $this->withoutVite()->actingAs($user)->get('/');

        $response->assertStatus(200);
    }
}
