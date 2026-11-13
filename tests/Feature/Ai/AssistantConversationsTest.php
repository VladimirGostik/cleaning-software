<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Contracts\Ai\ChatsWithTools;
use App\Enums\AssistantRoleEnum;
use App\Enums\PendingActionStatusEnum;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\PendingAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fakes\FakeAssistant;
use Tests\Support\CreatesMcpFixtures;
use Tests\TestCase;

final class AssistantConversationsTest extends TestCase
{
    use CreatesMcpFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->admin = $this->member($this->alpha, 'Admin');
        $this->app->instance(ChatsWithTools::class, new FakeAssistant);
    }

    private function conversation(?User $user = null, string $title = 'Téma'): AssistantConversation
    {
        $conversation = AssistantConversation::factory()->create(['user_id' => ($user ?? $this->admin)->id, 'title' => $title]);
        AssistantMessage::factory()->create(['assistant_conversation_id' => $conversation->id, 'role' => AssistantRoleEnum::User, 'content' => 'otázka', 'created_at' => now()->subMinute()]);
        AssistantMessage::factory()->assistant()->create(['assistant_conversation_id' => $conversation->id, 'content' => 'odpoveď', 'created_at' => now()]);

        return $conversation;
    }

    public function test_the_list_is_the_callers_own_newest_first_over_web_and_api(): void
    {
        $old = AssistantConversation::factory()->create(['user_id' => $this->admin->id, 'title' => 'Stará', 'last_message_at' => now()->subYears(2)]);
        $new = AssistantConversation::factory()->create(['user_id' => $this->admin->id, 'title' => 'Nová', 'last_message_at' => now()]);
        AssistantConversation::factory()->create(['title' => 'Cudzia']);

        $api = $this->apiAs($this->admin, $this->alpha)->getJson('/api/assistant/conversations')->assertOk();
        $api->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $new->id)->assertJsonPath('data.1.id', $old->id)->assertJsonPath('meta.per_page', 20)->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.last_page', 1)->assertJsonPath('meta.total', 2);
        $api->assertJsonStructure(['data' => [['id', 'title', 'last_message_at', 'created_at']], 'links', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        $this->webAs($this->admin, $this->alpha)->getJson(route('assistant.conversations.index'))->assertOk()->assertJsonPath('data.0.title', 'Nová');
    }

    public function test_the_list_paginates(): void
    {
        AssistantConversation::factory()->count(3)->create(['user_id' => $this->admin->id]);

        $this->apiAs($this->admin, $this->alpha)->getJson('/api/assistant/conversations?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.last_page', 2);
    }

    public function test_a_conversation_started_on_the_web_is_continued_over_the_api(): void
    {
        $id = $this->webAs($this->admin, $this->alpha)->postJson(route('assistant.messages'), [
            'company_id' => $this->alpha->id, 'conversation_id' => null, 'client_message_id' => (string) Str::uuid(), 'content' => 'Z webu',
        ])->assertOk()->json('conversation_id');
        $this->assertIsString($id);

        $this->apiAs($this->admin, $this->alpha)->getJson('/api/assistant/conversations')->assertJsonPath('data.0.id', $id);
        $this->apiAs($this->admin, $this->alpha)->postJson('/api/assistant/messages', [
            'company_id' => $this->alpha->id, 'conversation_id' => $id, 'client_message_id' => (string) Str::uuid(), 'content' => 'Z mobilu',
        ])->assertOk()->assertJsonPath('conversation_id', $id);

        $this->apiAs($this->admin, $this->alpha)->getJson('/api/assistant/conversations/'.$id)->assertJsonCount(4, 'messages');
    }

    public function test_show_returns_the_messages_oldest_first_with_the_live_pending_status(): void
    {
        $conversation = $this->conversation();
        $pending = PendingAction::factory()->create(['tenant_id' => $this->alpha->id, 'user_id' => $this->admin->id, 'status' => PendingActionStatusEnum::Executed]);
        AssistantMessage::query()->where('role', AssistantRoleEnum::Assistant)->update(['pending_action_id' => $pending->id]);

        $response = $this->apiAs($this->admin, $this->alpha)->getJson('/api/assistant/conversations/'.$conversation->id)->assertOk();

        $response->assertJsonPath('title', 'Téma')
            ->assertJsonPath('messages.0.content', 'otázka')
            ->assertJsonPath('messages.0.role', 'user')
            ->assertJsonPath('messages.1.content', 'odpoveď')
            ->assertJsonPath('messages.1.pending_action.status', 'executed')
            ->assertJsonPath('messages.0.pending_action', null);
        $response->assertJsonStructure(['id', 'title', 'last_message_at', 'messages' => [['id', 'role', 'content', 'attachment_names', 'actions', 'pending_action', 'security_notice', 'attachment_note', 'created_at']]]);
    }

    public function test_another_users_conversation_is_not_found(): void
    {
        $other = $this->member($this->alpha, 'Admin');
        $theirs = $this->conversation($other);
        $api = $this->apiAs($this->admin, $this->alpha);

        $api->getJson('/api/assistant/conversations/'.$theirs->id)->assertNotFound();
        $api->patchJson('/api/assistant/conversations/'.$theirs->id, ['title' => 'x'])->assertNotFound();
        $api->deleteJson('/api/assistant/conversations/'.$theirs->id)->assertNotFound();
        $api->getJson('/api/assistant/conversations')->assertJsonCount(0, 'data');

        $this->assertSame('Téma', $theirs->refresh()->title);
    }

    public function test_rename_limits(): void
    {
        $conversation = $this->conversation();
        $api = $this->apiAs($this->admin, $this->alpha);
        $url = '/api/assistant/conversations/'.$conversation->id;

        $api->patchJson($url, ['title' => str_repeat('a', 120)])->assertOk()->assertJsonPath('id', $conversation->id);
        $api->patchJson($url, ['title' => str_repeat('a', 121)])->assertUnprocessable()->assertJsonValidationErrors('title');
        $api->patchJson($url, ['title' => '   '])->assertUnprocessable()->assertJsonValidationErrors('title');
        $api->patchJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('title');

        $this->webAs($this->admin, $this->alpha)->patchJson(route('assistant.conversations.update', $conversation), ['title' => ' Nový názov '])->assertOk()->assertJsonPath('title', 'Nový názov');
        $this->assertSame('Nový názov', $conversation->refresh()->title);
    }

    public function test_delete_removes_both_tables_and_keeps_the_pending_action(): void
    {
        $conversation = $this->conversation();
        $pending = PendingAction::factory()->create(['tenant_id' => $this->alpha->id, 'user_id' => $this->admin->id]);
        AssistantMessage::query()->where('role', AssistantRoleEnum::Assistant)->update(['pending_action_id' => $pending->id]);

        $this->apiAs($this->admin, $this->alpha)->deleteJson('/api/assistant/conversations/'.$conversation->id)->assertNoContent();

        $this->assertSame(0, DB::table('assistant_conversations')->count());
        $this->assertSame(0, DB::table('assistant_messages')->count());
        $this->assertNotNull(PendingAction::query()->find($pending->id));
    }

    public function test_a_user_without_the_assistant_permission_is_refused(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');
        $this->grant($cleaner, $this->alpha, 'access mobile app');
        $own = $this->conversation($cleaner);
        $api = $this->apiAs($cleaner, $this->alpha);

        $api->getJson('/api/assistant/conversations')->assertForbidden();
        $api->getJson('/api/assistant/conversations/'.$own->id)->assertForbidden();
        $api->patchJson('/api/assistant/conversations/'.$own->id, ['title' => 'x'])->assertForbidden();
        $api->deleteJson('/api/assistant/conversations/'.$own->id)->assertNoContent();

        $this->assertSame(0, AssistantConversation::query()->count());
    }

    public function test_guests_are_refused(): void
    {
        $this->getJson('/api/assistant/conversations')->assertUnauthorized();
        $this->getJson(route('assistant.conversations.index'))->assertUnauthorized();
    }

    public function test_the_history_routes_are_throttled(): void
    {
        $statuses = array_map(fn (): int => $this->apiAs($this->admin, $this->alpha)->getJson('/api/assistant/conversations')->getStatusCode(), range(1, 61));

        $this->assertSame(429, $statuses[60]);
    }
}
