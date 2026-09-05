<?php

declare(strict_types=1);

namespace Tests\Feature\Language;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LanguageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_switch_to_sk_sets_session_locale_and_redirects(): void
    {
        $response = $this->get('/language/sk');

        $response->assertRedirect();
        $response->assertSessionHas('locale', 'sk');
    }

    public function test_switch_to_en_sets_session_locale(): void
    {
        $response = $this->get('/language/en');

        $response->assertRedirect();
        $response->assertSessionHas('locale', 'en');
    }

    public function test_switch_updates_authenticated_user_locale_in_database(): void
    {
        $user = User::factory()->create(['locale' => 'sk']);

        $this->actingAs($user)->get('/language/en');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'en']);
    }

    public function test_switch_with_unsupported_locale_returns_404(): void
    {
        $response = $this->get('/language/xx');

        $response->assertNotFound();
    }
}
