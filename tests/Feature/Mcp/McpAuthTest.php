<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\Support\CallsMcp;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class McpAuthTest extends TestCase
{
    use CallsMcp;
    use CreatesTaskFixtures;
    use RefreshDatabase;

    public function test_post_without_a_token_is_unauthenticated_with_a_www_authenticate_header(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], ['Accept' => 'application/json, text/event-stream'])
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    public function test_a_mobile_token_is_forbidden(): void
    {
        $this->seedPermissionCatalog();
        $user = $this->member($this->firm(), 'Admin');
        $token = $user->createToken('mobile', ['mobile'])->plainTextToken;

        $this->rpc($token, 'ping')->assertForbidden();
    }

    public function test_an_mcp_token_initializes_and_lists_every_tool_and_resource_template(): void
    {
        $this->seedPermissionCatalog();
        $user = $this->member($this->firm(), 'Admin');
        $token = $this->mcpToken($user);

        $this->rpc($token, 'initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'phpunit', 'version' => '1'],
        ])->assertOk()->assertJsonPath('result.serverInfo.name', 'KEGOS');

        /** @var list<string> $names */
        $names = $this->rpc($token, 'tools/list', ['per_page' => 100])->assertOk()->json('result.tools.*.name');

        foreach (['list_companies', 'company_overview', 'list_invoices', 'get_invoice', 'list_quotes', 'get_quote', 'list_clients', 'get_client', 'list_objects', 'list_jobs', 'list_tasks', 'get_task', 'list_expenses', 'search', 'create_task', 'update_task', 'complete_task', 'assign_task', 'create_expense', 'update_expense', 'create_quote_draft', 'update_quote_draft', 'create_invoice_draft', 'update_invoice_draft', 'prepare_action', 'confirm_action'] as $tool) {
            $this->assertContains($tool, $names);
        }

        foreach (['list_document_templates', 'get_document_template', 'update_document_template'] as $deferred) {
            $this->assertNotContains($deferred, $names);
        }

        $templates = $this->rpc($token, 'resources/templates/list')->assertOk()->json('result.resourceTemplates.*.uriTemplate');
        $this->assertEqualsCanonicalizing(['invoice-pdf://{company}/{invoice}', 'quote-pdf://{company}/{quote}'], $templates);
    }

    public function test_the_mcp_token_is_throttled_per_token(): void
    {
        config()->set('ai.mcp_throttle', 3);
        $this->seedPermissionCatalog();
        $user = $this->member($this->firm(), 'Admin');
        $token = $this->mcpToken($user);

        for ($i = 0; $i < 3; $i++) {
            $this->rpc($token, 'ping')->assertOk();
        }

        $this->rpc($token, 'ping')->assertTooManyRequests();

        $other = $this->mcpToken($user, 'second');
        $this->rpc($other, 'ping')->assertOk();
    }

    public function test_an_mcp_token_cannot_reach_the_mobile_api(): void
    {
        $this->seedPermissionCatalog();
        $user = $this->member($this->firm(), 'Admin');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->mcpToken($user))->getJson('/api/me')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->mcpToken($user))->getJson('/api/pending-actions')->assertForbidden();
    }

    public function test_get_is_not_allowed(): void
    {
        $this->getJson('/mcp')->assertStatus(405);
    }

    public function test_every_tool_is_listed_on_the_first_page_without_a_cursor(): void
    {
        $this->seedPermissionCatalog();
        $token = $this->mcpToken($this->member($this->firm(), 'Admin'));

        $response = $this->rpc($token, 'tools/list')->assertOk();

        /** @var list<string> $names */
        $names = $response->json('result.tools.*.name');
        $this->assertGreaterThanOrEqual(27, count($names));
        $this->assertContains('prepare_action', $names);
        $this->assertContains('confirm_action', $names);
        $this->assertContains('delete_global_task', $names);
        $this->assertNull($response->json('result.nextCursor'));
    }

    public function test_destructive_and_read_only_hints_are_published(): void
    {
        $this->seedPermissionCatalog();
        $token = $this->mcpToken($this->member($this->firm(), 'Admin'));

        $tools = array_column((array) $this->rpc($token, 'tools/list')->json('result.tools'), null, 'name');

        $this->assertTrue(Arr::get($tools, 'prepare_action.annotations.destructiveHint'));
        $this->assertTrue(Arr::get($tools, 'confirm_action.annotations.destructiveHint'));
        $this->assertTrue(Arr::get($tools, 'delete_global_task.annotations.destructiveHint'));
        $this->assertTrue(Arr::get($tools, 'list_invoices.annotations.readOnlyHint'));
        $this->assertFalse(Arr::get($tools, 'create_task.annotations.destructiveHint'));
    }

    public function test_a_deactivated_user_loses_mcp_and_mobile_access_at_once(): void
    {
        $this->seedPermissionCatalog();
        $user = $this->member($this->firm(), 'Admin');
        $mcp = $this->mcpToken($user);
        $mobile = $user->createToken('mobile', ['mobile'])->plainTextToken;

        $this->rpc($mcp, 'ping')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($mobile)->getJson('/api/me')->assertOk();

        $user->update(['is_active' => false]);

        $this->rpc($mcp, 'ping')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($mobile)->getJson('/api/me')->assertForbidden();
    }

    public function test_the_api_error_language_follows_the_user_over_the_header_and_guests_follow_the_header(): void
    {
        $this->seedPermissionCatalog();
        $user = $this->member($this->firm(), 'Admin');
        $user->update(['locale' => 'en']);
        $mobile = $user->createToken('mobile', ['mobile'])->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($mobile)->withHeader('Accept-Language', 'uk')->putJson('/api/profile/password', [])->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'required'));

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->withHeader('Accept-Language', 'uk')->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', trans('app.invalid_credentials', [], 'uk'));
    }

    public function test_an_mcp_tool_error_follows_the_users_locale(): void
    {
        $this->seedPermissionCatalog();
        $user = $this->member($this->firm(), 'Admin');
        $other = $this->firm('Other');

        $user->update(['locale' => 'en']);
        $english = $this->callTool($this->mcpToken($user), 'list_tasks', ['company_id' => $other->id]);

        $user->update(['locale' => 'sk']);
        $slovak = $this->callTool($this->mcpToken($user), 'list_tasks', ['company_id' => $other->id]);

        $this->assertSame('You are not allowed to do this.', $english->text);
        $this->assertSame('Túto akciu nemáte povolenú.', $slovak->text);
    }
}
