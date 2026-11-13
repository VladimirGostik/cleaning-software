<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Contracts\Ai\ChatsWithTools;
use App\Contracts\Ai\RunsTasks;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\LaravelAiAssistant;
use App\Services\Ai\LaravelAiTaskRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\CreatesTaskRunFixtures;
use Tests\Support\FakesAnthropicProvider;
use Tests\TestCase;

final class AiRequestOptionsTest extends TestCase
{
    use CreatesTaskRunFixtures;
    use FakesAnthropicProvider;
    use RefreshDatabase;

    private const string JPEG = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

    private const array USAGE_KEYS = ['model', 'thinking', 'effort', 'duration_ms', 'completed', 'steps', 'input_tokens', 'output_tokens', 'cache_read_input_tokens', 'cache_creation_input_tokens'];

    /** @var list<MessageLogged> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTaskRunFixtures();
        $this->app->bind(ChatsWithTools::class, LaravelAiAssistant::class);
        $this->app->forgetInstance(RunsTasks::class);
        $this->app->bind(RunsTasks::class, LaravelAiTaskRunner::class);
        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $this->logged[] = $event;
        });
    }

    /** @param  list<UploadedFile>  $files */
    private function chat(array $files = [], ?User $user = null, ?Tenant $tenant = null): void
    {
        $user ??= $this->admin;
        $tenant ??= $this->alpha;

        $this->apiAs($user, $tenant)->post('/api/assistant/messages', [
            'company_id' => $tenant->id,
            'conversation_id' => null,
            'client_message_id' => (string) Str::uuid(),
            'content' => 'Ahoj',
            ...($files === [] ? [] : ['attachments' => $files]),
        ], ['Accept' => 'application/json']);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bon.jpg', (string) base64_decode(self::JPEG));
    }

    /** @return array<string, mixed> */
    private function usageLines(string $event): array
    {
        $lines = array_values(array_filter($this->logged, static fn (MessageLogged $log): bool => $log->message === $event));
        $this->assertCount(1, $lines);
        $this->assertSame('info', $lines[0]->level);

        /** @var array<string, mixed> $context */
        $context = $lines[0]->context;

        return $context;
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $request->data();

        return $data;
    }

    private function systemText(Request $request, int $block): string
    {
        return Arr::string($this->body($request), "system.{$block}.text");
    }

    /** @return array<string, mixed> */
    private function thinkingTurn(): array
    {
        $turn = $this->toolTurn([['name' => 'list_companies', 'input' => []]]);
        $turn['content'] = [['type' => 'thinking', 'thinking' => 'checking', 'signature' => 'sig'], ...(array) $turn['content']];
        $turn['usage'] = ['input_tokens' => 100, 'output_tokens' => 20, 'cache_creation_input_tokens' => 50, 'cache_read_input_tokens' => 7];

        return $turn;
    }

    /** @return array<string, mixed> */
    private function finalTurn(): array
    {
        $turn = $this->textTurn('hotovo');
        $turn['usage'] = ['input_tokens' => 30, 'output_tokens' => 5, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 120];

        return $turn;
    }

    public function test_a_text_turn_sends_thinking_effort_and_cached_system_blocks(): void
    {
        $this->providerSays([$this->textTurn('Ahoj')]);

        $this->chat();

        $body = $this->body($this->sentRequests()[0]);
        $this->assertSame(['type' => 'between_tools'], $body['thinking']);
        $this->assertSame(['effort' => 'medium'], $body['output_config']);
        $this->assertSame(['type' => 'ephemeral'], $body['cache_control']);
        $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], data_get($body, 'system.0.cache_control'));
        $this->assertArrayNotHasKey('cache_control', (array) data_get($body, 'system.1'));
    }

    public function test_an_attachment_turn_omits_thinking_but_keeps_effort(): void
    {
        $this->providerSays([$this->textTurn('Ahoj')]);

        $this->chat([$this->image()]);

        $body = $this->body($this->sentRequests()[0]);
        $this->assertArrayNotHasKey('thinking', $body);
        $this->assertSame(['effort' => 'medium'], $body['output_config']);
        $this->assertSame('default', $this->usageLines('ai.assistant_usage')['thinking']);
    }

    public function test_an_unknown_model_sends_no_thinking_key(): void
    {
        config(['ai.model' => 'claude-opus-5-5']);
        $this->providerSays([$this->textTurn('Ahoj')]);

        $this->chat();

        $this->assertArrayNotHasKey('thinking', $this->body($this->sentRequests()[0]));
    }

    public function test_the_rules_block_is_byte_identical_across_users_companies_dates_and_files(): void
    {
        $second = $this->member($this->beta, 'Admin');
        $this->providerSays([$this->textTurn('a'), $this->textTurn('b')]);

        $this->travelTo(now()->setDate(2026, 3, 2));
        $this->chat();
        $this->travelTo(now()->setDate(2026, 9, 30));
        $this->chat([$this->image()], $second, $this->beta);

        [$first, $next] = $this->sentRequests();
        $rules = $this->systemText($first, 0);

        $this->assertSame($rules, $this->systemText($next, 0));
        $this->assertStringNotContainsString($this->alpha->id, $rules);
        $this->assertStringNotContainsString('tool_output_', $rules);
        $this->assertStringNotContainsString('2026-', $rules);
        $this->assertStringContainsString($this->alpha->id, $this->systemText($first, 1));
        $this->assertStringContainsString('tool_output_', $this->systemText($first, 1));
        $this->assertStringContainsString('2026-03-02', $this->systemText($first, 1));
        $this->assertStringContainsString($this->beta->id, $this->systemText($next, 1));
        $this->assertStringContainsString('2026-09-30', $this->systemText($next, 1));
        $this->assertStringContainsString('bon.jpg', $this->systemText($next, 1));
    }

    public function test_the_tool_loop_replays_thinking_unchanged_and_keeps_the_request_options(): void
    {
        $this->providerSays([$this->thinkingTurn(), $this->finalTurn()]);

        $this->chat();

        $second = $this->body($this->sentRequests()[1]);
        $assistant = array_values(array_filter((array) $second['messages'], static fn (mixed $message): bool => is_array($message) && $message['role'] === 'assistant'));
        $thinking = array_values(array_filter((array) $assistant[0]['content'], static fn (mixed $block): bool => is_array($block) && $block['type'] === 'thinking'));

        $this->assertSame([['type' => 'thinking', 'thinking' => 'checking', 'signature' => 'sig']], $thinking);

        foreach (['system', 'thinking', 'output_config', 'cache_control'] as $key) {
            $this->assertSame($this->body($this->sentRequests()[0])[$key], $second[$key]);
        }
    }

    public function test_one_usage_line_per_turn_with_summed_tokens(): void
    {
        $this->providerSays([$this->thinkingTurn(), $this->finalTurn()]);

        $this->chat();

        $context = $this->usageLines('ai.assistant_usage');
        $this->assertEqualsCanonicalizing(self::USAGE_KEYS, array_keys($context));
        $this->assertSame('claude-sonnet-5-5', $context['model']);
        $this->assertSame('between_tools', $context['thinking']);
        $this->assertSame('medium', $context['effort']);
        $this->assertTrue($context['completed']);
        $this->assertSame(2, $context['steps']);
        $this->assertSame(130, $context['input_tokens']);
        $this->assertSame(25, $context['output_tokens']);
        $this->assertSame(127, $context['cache_read_input_tokens']);
        $this->assertSame(50, $context['cache_creation_input_tokens']);
        $this->assertIsInt($context['duration_ms']);
    }

    public function test_a_provider_failure_logs_an_incomplete_turn_without_token_keys(): void
    {
        $this->providerSays([], thenServerError: true);

        $this->chat();

        $context = $this->usageLines('ai.assistant_usage');
        $this->assertEqualsCanonicalizing(['model', 'thinking', 'effort', 'duration_ms', 'completed'], array_keys($context));
        $this->assertFalse($context['completed']);
    }

    public function test_a_task_run_sends_thinking_effort_split_system_and_logs_usage(): void
    {
        config(['ai.task_run.effort' => 'high']);
        $task = $this->firmTask();
        $this->providerSays([
            $this->toolTurn([['name' => 'report_task_outcome', 'input' => ['outcome' => 'done', 'message' => 'ok']]]),
            $this->textTurn('ok'),
        ]);

        $this->execute($this->queuedRun($task, $this->admin));

        $body = $this->body($this->sentRequests()[0]);
        $this->assertSame(['type' => 'between_tools'], $body['thinking']);
        $this->assertSame(['effort' => 'high'], $body['output_config']);
        $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], data_get($body, 'system.0.cache_control'));
        $this->assertStringNotContainsString($this->alpha->id, $this->systemText($this->sentRequests()[0], 0));
        $this->assertStringContainsString($this->alpha->id, $this->systemText($this->sentRequests()[0], 1));
        $this->assertStringContainsString('task_data_', $this->systemText($this->sentRequests()[0], 1));

        $context = $this->usageLines('ai.task_run_usage');
        $this->assertEqualsCanonicalizing(self::USAGE_KEYS, array_keys($context));
        $this->assertSame('high', $context['effort']);
        $this->assertTrue($context['completed']);
    }

    public function test_a_failed_task_run_logs_an_incomplete_turn(): void
    {
        $task = $this->firmTask();
        $this->providerSays([], thenServerError: true);

        $this->execute($this->queuedRun($task, $this->admin));

        $this->assertFalse($this->usageLines('ai.task_run_usage')['completed']);
    }
}
