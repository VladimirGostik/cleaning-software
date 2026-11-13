<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Contracts\Ai\ChatsWithTools;
use App\Data\Ai\AssistantActionData;
use App\Data\Ai\AssistantReplyData;
use App\Data\Ai\PendingActionData;
use App\Enums\AssistantActionTypeEnum;
use App\Enums\AssistantRoleEnum;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\PendingAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeAssistant;
use Tests\Support\CreatesMcpFixtures;
use Tests\TestCase;

final class AssistantHistoryTest extends TestCase
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
        $this->member($this->beta, 'Admin', $this->admin);
        $this->app->instance(ChatsWithTools::class, $this->fake = new FakeAssistant);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<JsonResponse>
     */
    private function send(array $body = [], ?User $user = null, ?Tenant $tenant = null): TestResponse
    {
        return $this->apiAs($user ?? $this->admin, $tenant ?? $this->alpha)->postJson('/api/assistant/messages', [
            'company_id' => ($tenant ?? $this->alpha)->id,
            'conversation_id' => null,
            'client_message_id' => (string) Str::uuid(),
            'content' => 'Ahoj',
            ...$body,
        ]);
    }

    /** @param  list<array{0: AssistantRoleEnum, 1: string}>  $rows */
    private function seedRows(AssistantConversation $conversation, array $rows): void
    {
        foreach ($rows as $i => [$role, $content]) {
            AssistantMessage::factory()->create([
                'assistant_conversation_id' => $conversation->id,
                'role' => $role,
                'content' => $content,
                'created_at' => now()->subHours(100)->addSeconds($i),
            ]);
        }
    }

    public function test_the_first_message_creates_a_conversation_and_the_second_continues_it(): void
    {
        $first = $this->send(['content' => 'Koľko mám faktúr?'])->assertOk();
        $id = $first->json('conversation_id');

        $first->assertJsonPath('conversation_title', 'Koľko mám faktúr?');
        $this->assertSame(1, AssistantConversation::query()->count());
        $this->assertSame(2, AssistantMessage::query()->count());

        $this->send(['conversation_id' => $id, 'content' => 'A po splatnosti?'])->assertOk()->assertJsonPath('conversation_id', $id);

        $this->assertSame(1, AssistantConversation::query()->count());
        $this->assertSame(4, AssistantMessage::query()->count());
        $this->assertSame([], $this->fake->histories[0]);
        $this->assertSame(
            [[AssistantRoleEnum::User, 'Koľko mám faktúr?'], [AssistantRoleEnum::Assistant, 'Fake reply']],
            array_map(static fn ($m): array => [$m->role, $m->content], $this->fake->histories[1]),
        );
    }

    public function test_the_conversation_continues_in_another_company(): void
    {
        $id = $this->send()->json('conversation_id');

        $this->send(['conversation_id' => $id, 'company_id' => $this->beta->id], tenant: $this->beta)->assertOk()->assertJsonPath('conversation_id', $id);

        $this->assertSame(4, AssistantMessage::query()->where('assistant_conversation_id', $id)->count());
    }

    public function test_a_conversation_two_years_old_is_continuable(): void
    {
        $conversation = AssistantConversation::factory()->create(['user_id' => $this->admin->id, 'last_message_at' => now()->subYears(2)]);
        $this->seedRows($conversation, [[AssistantRoleEnum::User, 'stará'], [AssistantRoleEnum::Assistant, 'odpoveď']]);

        $this->send(['conversation_id' => $conversation->id])->assertOk();

        $this->assertCount(2, $this->fake->histories[0]);
    }

    public function test_a_foreign_or_missing_conversation_is_refused(): void
    {
        $other = AssistantConversation::factory()->create();

        $this->send(['conversation_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('conversation_id');
        $this->send(['conversation_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('conversation_id');

        $this->assertSame([], $this->fake->requests);
    }

    public function test_replaying_a_client_message_id_returns_the_stored_reply_without_the_ai(): void
    {
        $id = (string) Str::uuid();
        $first = $this->send(['client_message_id' => $id])->assertOk();

        $this->fake->reply = new AssistantReplyData('Different', [], null);
        $second = $this->send(['client_message_id' => $id])->assertOk();

        $this->assertSame($first->json(), $second->json());
        $this->assertCount(1, $this->fake->requests);
        $this->assertSame(2, AssistantMessage::query()->count());
    }

    public function test_replay_does_not_attach_files_again(): void
    {
        $id = (string) Str::uuid();
        $file = UploadedFile::fake()->image('bon.jpg');

        $this->apiAs($this->admin, $this->alpha)->post('/api/assistant/messages', [
            'company_id' => $this->alpha->id, 'client_message_id' => $id, 'content' => 'zapíš', 'attachments' => [$file],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->send(['client_message_id' => $id, 'content' => 'zapíš'])->assertOk();

        $this->assertCount(1, $this->fake->requests);
    }

    public function test_a_client_message_id_of_another_user_is_refused(): void
    {
        $other = $this->member($this->alpha, 'Admin');
        $id = (string) Str::uuid();
        $this->send(['client_message_id' => $id], $other)->assertOk();

        $this->send(['client_message_id' => $id])->assertUnprocessable()->assertJsonValidationErrors('client_message_id');
    }

    public function test_an_unavailable_ai_stores_nothing(): void
    {
        $this->fake->unavailable = true;

        $this->send()->assertUnprocessable()->assertJsonValidationErrors('ai');

        $this->assertSame(0, AssistantConversation::query()->count());
        $this->assertSame(0, AssistantMessage::query()->count());
    }

    public function test_a_validation_error_stores_nothing(): void
    {
        $this->send(['content' => ''])->assertUnprocessable();

        $this->assertSame(0, AssistantConversation::query()->count());
    }

    public function test_a_reply_with_actions_and_a_pending_action_is_stored(): void
    {
        $pending = PendingAction::factory()->create(['tenant_id' => $this->alpha->id, 'user_id' => $this->admin->id]);
        $this->fake->reply = new AssistantReplyData(
            'Pripravené.',
            [new AssistantActionData(AssistantActionTypeEnum::cases()[0], 'Úloha', '/tasks/1', $this->alpha->id)],
            PendingActionData::fromModel($pending),
            'upozornenie',
            'poznámka',
        );

        $this->send()->assertOk();

        $row = AssistantMessage::query()->where('role', AssistantRoleEnum::Assistant)->sole();
        $this->assertSame($pending->id, $row->pending_action_id);
        $this->assertSame('Úloha', $row->actions[0]['label'] ?? null);
        $this->assertSame('upozornenie', $row->security_notice);
        $this->assertSame('poznámka', $row->attachment_note);
    }

    public function test_attachment_names_are_stored_without_any_media(): void
    {
        $files = [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')];

        $this->apiAs($this->admin, $this->alpha)->post('/api/assistant/messages', [
            'company_id' => $this->alpha->id, 'client_message_id' => (string) Str::uuid(), 'content' => '', 'attachments' => $files,
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('conversation_title', 'a.jpg');

        $row = AssistantMessage::query()->where('role', AssistantRoleEnum::User)->sole();
        $this->assertSame(['a.jpg', 'b.jpg'], $row->attachment_names);
        $this->assertSame(0, DB::table('media')->where('model_type', '!=', 'task')->whereIn('model_type', [AssistantConversation::class, AssistantMessage::class])->count());
    }

    public function test_the_history_window_keeps_the_last_nineteen_messages(): void
    {
        $conversation = AssistantConversation::factory()->create(['user_id' => $this->admin->id]);
        $rows = [];

        for ($i = 0; $i < 30; $i++) {
            $rows[] = [$i % 2 === 0 ? AssistantRoleEnum::User : AssistantRoleEnum::Assistant, 'm'.$i];
        }

        $this->seedRows($conversation, $rows);
        $this->send(['conversation_id' => $conversation->id])->assertOk();

        $history = $this->fake->histories[0];
        $this->assertCount(19, $history);
        $this->assertSame('m11', $history[0]->content);
        $this->assertSame('m29', $history[18]->content);
    }

    public function test_the_history_window_drops_the_oldest_over_the_character_budget(): void
    {
        $conversation = AssistantConversation::factory()->create(['user_id' => $this->admin->id]);
        $this->seedRows($conversation, [
            [AssistantRoleEnum::User, str_repeat('a', 4000)],
            [AssistantRoleEnum::Assistant, str_repeat('b', 4000)],
            [AssistantRoleEnum::User, str_repeat('c', 4000)],
            [AssistantRoleEnum::Assistant, str_repeat('d', 3000)],
        ]);

        $this->send(['conversation_id' => $conversation->id, 'content' => str_repeat('e', 1000)])->assertOk();

        $this->assertSame(
            [str_repeat('b', 4000), str_repeat('c', 4000), str_repeat('d', 3000)],
            array_map(static fn ($m): string => $m->content, $this->fake->histories[0]),
        );
    }

    public function test_a_full_conversation_refuses_another_message(): void
    {
        $conversation = AssistantConversation::factory()->create(['user_id' => $this->admin->id]);
        AssistantMessage::factory()->count(199)->create(['assistant_conversation_id' => $conversation->id]);

        $this->send(['conversation_id' => $conversation->id])->assertUnprocessable()->assertJsonValidationErrors('content');

        $this->assertSame([], $this->fake->requests);
    }

    public function test_content_title_and_names_are_encrypted_at_rest(): void
    {
        $this->send(['content' => 'Tajný text'])->assertOk();

        $this->assertNotSame('Tajný text', DB::table('assistant_messages')->where('role', 'user')->value('content'));
        $this->assertNotSame('Tajný text', DB::table('assistant_conversations')->value('title'));
        $this->assertSame('Tajný text', AssistantMessage::query()->where('role', AssistantRoleEnum::User)->sole()->content);
        $this->assertSame('Tajný text', AssistantConversation::query()->sole()->title);
    }

    public function test_a_long_first_message_is_cut_into_the_title(): void
    {
        $this->send(['content' => str_repeat('x', 200)])->assertOk();

        $this->assertSame(80, mb_strlen(AssistantConversation::query()->sole()->title));
    }

    public function test_the_title_squishes_whitespace(): void
    {
        $this->send(['content' => "  a \n\n b   c "])->assertOk()->assertJsonPath('conversation_title', 'a b c');
    }

    public function test_replay_picks_the_answer_of_the_same_turn_when_rows_share_a_second(): void
    {
        $conversation = AssistantConversation::factory()->create(['user_id' => $this->admin->id]);
        $at = now()->startOfSecond();
        $id = (string) Str::uuid();

        foreach ([[AssistantRoleEnum::User, 'first', null], [AssistantRoleEnum::Assistant, 'answer one', null], [AssistantRoleEnum::User, 'second', $id], [AssistantRoleEnum::Assistant, 'answer two', null]] as [$role, $content, $clientId]) {
            AssistantMessage::factory()->create(['assistant_conversation_id' => $conversation->id, 'role' => $role, 'content' => $content, 'client_message_id' => $clientId, 'created_at' => $at]);
        }

        $this->send(['client_message_id' => $id])->assertOk()->assertJsonPath('reply', 'answer two');
        $this->assertSame([], $this->fake->requests);
    }

    public function test_a_conversation_deleted_during_the_turn_is_recreated(): void
    {
        $conversation = AssistantConversation::factory()->create(['user_id' => $this->admin->id]);
        $this->fake->onReply = function () use ($conversation): void {
            AssistantConversation::query()->whereKey($conversation->id)->delete();
        };

        $response = $this->send(['conversation_id' => $conversation->id, 'content' => 'Pokračuj'])->assertOk();

        $this->assertNotSame($conversation->id, $response->json('conversation_id'));
        $response->assertJsonPath('conversation_title', 'Pokračuj');
        $this->assertSame(1, AssistantConversation::query()->count());
        $this->assertSame(2, AssistantMessage::query()->count());
    }

    public function test_a_retry_while_the_first_send_is_running_is_refused(): void
    {
        config(['ai.assistant.history.lock_wait_seconds' => 0]);
        $id = (string) Str::uuid();
        $lock = Cache::lock('assistant-turn:'.$this->admin->id.':'.$id, 30);
        $this->assertTrue($lock->get());

        $this->send(['client_message_id' => $id])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame([], $this->fake->requests);
        $lock->release();
        $this->send(['client_message_id' => $id])->assertOk();
    }

    public function test_a_retry_after_the_lock_timeout_gets_the_stored_reply(): void
    {
        config(['ai.assistant.history.lock_wait_seconds' => 0]);
        $id = (string) Str::uuid();
        $first = $this->send(['client_message_id' => $id])->assertOk();
        $lock = Cache::lock('assistant-turn:'.$this->admin->id.':'.$id, 30);
        $this->assertTrue($lock->get());

        $this->send(['client_message_id' => $id])->assertOk()->assertJsonPath('conversation_id', $first->json('conversation_id'));
        $this->assertCount(1, $this->fake->requests);
    }

    public function test_a_unique_violation_on_the_client_message_id_falls_back_to_the_stored_reply(): void
    {
        $id = (string) Str::uuid();
        $this->fake->onReply = function () use ($id): void {
            $rival = AssistantConversation::factory()->create(['user_id' => $this->admin->id, 'title' => 'Súper']);
            AssistantMessage::factory()->create(['assistant_conversation_id' => $rival->id, 'role' => AssistantRoleEnum::User, 'client_message_id' => $id, 'created_at' => now()->subSecond()]);
            AssistantMessage::factory()->assistant()->create(['assistant_conversation_id' => $rival->id, 'content' => 'rival reply', 'created_at' => now()]);
        };

        $this->send(['client_message_id' => $id])->assertOk()->assertJsonPath('reply', 'rival reply')->assertJsonPath('conversation_title', 'Súper');

        $this->assertSame(1, AssistantConversation::query()->count());
        $this->assertSame(2, AssistantMessage::query()->count());
    }
}
