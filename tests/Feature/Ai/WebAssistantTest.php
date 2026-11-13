<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Contracts\Ai\ChatsWithTools;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Fakes\FakeAssistant;
use Tests\Support\CreatesMcpFixtures;
use Tests\TestCase;

final class WebAssistantTest extends TestCase
{
    use CreatesMcpFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $admin;

    private FakeAssistant $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->admin = $this->member($this->alpha, 'Admin');
        $this->app->instance(ChatsWithTools::class, $this->fake = new FakeAssistant);
    }

    /** @return array{company_id: string, conversation_id: null, client_message_id: string, content: string} */
    private function payload(?Tenant $tenant = null): array
    {
        return ['company_id' => ($tenant ?? $this->alpha)->id, 'conversation_id' => null, 'client_message_id' => (string) Str::uuid(), 'content' => 'Ahoj'];
    }

    public function test_a_member_with_the_permission_chats_in_the_active_company(): void
    {
        $this->webAs($this->admin, $this->alpha)
            ->postJson(route('assistant.messages'), $this->payload())
            ->assertOk()
            ->assertJsonStructure(['reply', 'actions', 'pending_action', 'security_notice', 'attachment_note', 'conversation_id', 'conversation_title']);

        $this->assertSame($this->alpha->id, $this->fake->requests[0]->company_id);
        $this->assertSame('Ahoj', $this->fake->requests[0]->content);
    }

    public function test_a_chat_may_target_another_company_of_the_user_than_the_session_tenant(): void
    {
        $this->member($this->beta, 'Admin', $this->admin);

        $this->webAs($this->admin, $this->alpha)
            ->postJson(route('assistant.messages'), $this->payload($this->beta))
            ->assertOk();

        $this->assertSame($this->beta->id, $this->fake->requests[0]->company_id);
    }

    public function test_vedúca_holds_the_permission_by_default(): void
    {
        $leader = $this->member($this->alpha, 'Vedúca');

        $this->webAs($leader, $this->alpha)->postJson(route('assistant.messages'), $this->payload())->assertOk();
    }

    public function test_a_member_without_the_permission_is_refused_even_when_an_admin_elsewhere(): void
    {
        $user = $this->member($this->alpha, 'Interná upratovačka');
        $this->member($this->beta, 'Admin', $user);

        $this->webAs($user, $this->alpha)
            ->postJson(route('assistant.messages'), $this->payload())
            ->assertForbidden()
            ->assertJsonPath('message', __('app.assistant_forbidden'));

        $this->assertSame([], $this->fake->requests);
    }

    public function test_a_company_the_user_is_not_a_member_of_is_refused_with_the_mobile_forbidden_shape(): void
    {
        $this->webAs($this->admin, $this->alpha)
            ->postJson(route('assistant.messages'), $this->payload($this->beta))
            ->assertForbidden()
            ->assertJsonPath('message', __('app.mobile_forbidden'));

        $this->assertSame([], $this->fake->requests);
    }

    public function test_a_guest_is_unauthenticated(): void
    {
        $this->postJson(route('assistant.messages'), $this->payload())->assertUnauthorized();
    }

    public function test_the_provider_being_unavailable_is_a_validation_error_on_ai(): void
    {
        $this->fake->unavailable = true;

        $this->webAs($this->admin, $this->alpha)
            ->postJson(route('assistant.messages'), $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ai');
    }

    public function test_the_route_is_throttled_per_user(): void
    {
        config(['ai.throttle.assistant' => 3]);

        $statuses = array_map(
            fn (): int => $this->webAs($this->admin, $this->alpha)->postJson(route('assistant.messages'), $this->payload())->getStatusCode(),
            range(1, 4),
        );

        $this->assertSame([200, 200, 200, 429], $statuses);
    }

    public function test_the_route_is_protected_by_csrf(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn (Application $app): PreventRequestForgery => new class($app, $app->make(Encrypter::class)) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });

        $this->webAs($this->admin, $this->alpha)
            ->post(route('assistant.messages'), $this->payload())
            ->assertStatus(419);

        $this->assertSame([], $this->fake->requests);
    }
}
