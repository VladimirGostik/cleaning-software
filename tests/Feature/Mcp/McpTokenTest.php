<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Models\Activity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\CreatesMcpFixtures;
use Tests\TestCase;

final class McpTokenTest extends TestCase
{
    use CreatesMcpFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->user = $this->member($this->alpha, 'Admin');
    }

    public function test_the_profile_page_lists_only_the_users_own_mcp_tokens(): void
    {
        $this->user->createToken('claude', ['mcp']);
        $this->user->createToken('phone', ['mobile']);
        $stranger = $this->member($this->alpha, 'Admin');
        $stranger->createToken('theirs', ['mcp']);

        $this->webAs($this->user, $this->alpha)->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Show')
                ->has('mcpTokens', 1)
                ->where('mcpTokens.0.name', 'claude')
                ->where('mcpUrl', url('/mcp'))
                ->missing('mcpTokens.0.plain_text_token')
                ->missing('mcpTokens.0.token'));
    }

    public function test_creating_a_token_flashes_the_plain_text_once_and_stores_only_the_mcp_ability(): void
    {
        $this->webAs($this->user, $this->alpha)
            ->post(route('profile.mcp-tokens.store'), ['name' => 'Claude Desktop'])
            ->assertRedirect();

        $stored = PersonalAccessToken::query()->sole();
        $this->assertSame(['mcp'], $stored->abilities);
        $this->assertSame('Claude Desktop', $stored->name);

        $this->webAs($this->user, $this->alpha)->get(route('profile.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('mcpToken.plain_text_token')->hasFlash('mcpToken.token.name', 'Claude Desktop'));

        $this->webAs($this->user, $this->alpha)->get(route('profile.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page->missingFlash('mcpToken'));
    }

    public function test_the_json_response_carries_the_plain_text_which_then_works_on_mcp_but_not_on_the_mobile_api(): void
    {
        $response = $this->webAs($this->user, $this->alpha)
            ->postJson(route('profile.mcp-tokens.store'), ['name' => 'Inspector'])
            ->assertCreated()
            ->assertJsonStructure(['token' => ['id', 'name', 'created_at', 'last_used_at'], 'plain_text_token']);

        $plain = Arr::string((array) $response->json(), 'plain_text_token');
        $this->assertNotSame($plain, PersonalAccessToken::query()->sole()->token);
        $this->assertStringNotContainsString($plain, (string) json_encode(Activity::query()->get()->toArray()));

        $this->rpc($plain, 'ping')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->getJson('/api/me')->assertForbidden();
    }

    public function test_the_name_is_validated(): void
    {
        $this->webAs($this->user, $this->alpha)->postJson(route('profile.mcp-tokens.store'), ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->webAs($this->user, $this->alpha)->postJson(route('profile.mcp-tokens.store'), ['name' => str_repeat('a', 61)])->assertUnprocessable();
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_a_guest_cannot_create_or_delete_tokens(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson(route('profile.mcp-tokens.store'), ['name' => 'x'])->assertUnauthorized();
        $this->deleteJson(route('profile.mcp-tokens.destroy', 1))->assertUnauthorized();
    }

    public function test_deleting_revokes_the_users_own_mcp_token(): void
    {
        $plain = $this->mcpToken($this->user);
        $id = PersonalAccessToken::query()->sole()->getKey();

        $this->webAs($this->user, $this->alpha)->delete(route('profile.mcp-tokens.destroy', $id))->assertRedirect();

        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->rpc($plain, 'ping')->assertUnauthorized();
    }

    public function test_a_mobile_token_or_a_foreign_token_is_not_found_through_this_route(): void
    {
        $mobile = $this->user->createToken('phone', ['mobile'])->accessToken;
        $stranger = $this->member($this->alpha, 'Admin');
        $foreign = $stranger->createToken('theirs', ['mcp'])->accessToken;

        $this->webAs($this->user, $this->alpha)->delete(route('profile.mcp-tokens.destroy', $mobile->getKey()))->assertNotFound();
        $this->webAs($this->user, $this->alpha)->delete(route('profile.mcp-tokens.destroy', $foreign->getKey()))->assertNotFound();
        $this->webAs($this->user, $this->alpha)->delete(route('profile.mcp-tokens.destroy', 999999))->assertNotFound();

        $this->assertSame(2, PersonalAccessToken::query()->count());
    }

    public function test_a_token_with_both_abilities_is_not_manageable_here(): void
    {
        $both = $this->user->createToken('mixed', ['mcp', 'mobile'])->accessToken;

        $this->webAs($this->user, $this->alpha)->get(route('profile.show'))->assertInertia(fn (AssertableInertia $page) => $page->has('mcpTokens', 0));
        $this->webAs($this->user, $this->alpha)->delete(route('profile.mcp-tokens.destroy', $both->getKey()))->assertNotFound();
        $this->assertModelExists($both);
    }

    public function test_tokens_expire_after_180_days_and_an_expired_token_is_rejected(): void
    {
        $response = $this->webAs($this->user, $this->alpha)->postJson(route('profile.mcp-tokens.store'), ['name' => 'Short lived'])->assertCreated();

        $stored = PersonalAccessToken::query()->sole();
        $this->assertNotNull($stored->expires_at);
        $this->assertEqualsWithDelta(180, now()->diffInDays($stored->expires_at, true), 1);
        $this->assertNotNull($response->json('token.expires_at'));

        $plain = Arr::string((array) $response->json(), 'plain_text_token');
        $this->rpc($plain, 'ping')->assertOk();

        $stored->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->rpc($plain, 'ping')->assertUnauthorized();
    }

    public function test_a_user_may_hold_at_most_ten_mcp_tokens(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->user->createToken('t'.$i, ['mcp']);
        }
        $this->user->createToken('phone', ['mobile']);

        $this->webAs($this->user, $this->alpha)->postJson(route('profile.mcp-tokens.store'), ['name' => 'eleventh'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame(11, PersonalAccessToken::query()->count());
    }
}
