<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Contracts\Ai\ExtractsTaskDraft;
use App\Enums\TaskPriorityEnum;
use App\Exceptions\AiUnavailableException;
use App\Models\Task;
use App\Services\Ai\LaravelAiTaskExtractor;
use App\Services\Ai\TaskExtractionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\Support\CreatesMcpFixtures;
use Tests\TestCase;

final class LaravelAiTaskExtractorTest extends TestCase
{
    use CreatesMcpFixtures;
    use RefreshDatabase;

    private function context(?string $companyId = 'c1'): TaskExtractionContext
    {
        return new TaskExtractionContext('2026-11-02', 'Europe/Bratislava', [['id' => 'c1', 'name' => 'Alpha']], $companyId, [['id' => 'm1', 'name' => 'Jana Nováková']]);
    }

    /** @param  array<string, mixed>  $structured */
    private function fakeProvider(array $structured): void
    {
        config(['ai.providers.anthropic.key' => 'test-key']);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5',
            'content' => [['type' => 'text', 'text' => json_encode($structured)]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ])]);
    }

    public function test_the_structured_output_is_mapped_and_the_prompt_carries_the_date_timezone_and_lists(): void
    {
        $this->fakeProvider([
            'title' => 'Zavolať klientovi', 'description' => null, 'due_at' => '2026-11-03T09:00:00+01:00', 'priority' => 'high',
            'company_id' => 'c1', 'assignee_member_id' => 'm1', 'assignee_hint' => 'Jana',
        ]);

        $draft = app(ExtractsTaskDraft::class)->extract('zavolať klientovi zajtra o 9, Jana', $this->context());

        $this->assertInstanceOf(LaravelAiTaskExtractor::class, app(ExtractsTaskDraft::class));
        $this->assertSame('Zavolať klientovi', $draft->title);
        $this->assertSame('2026-11-03T09:00:00+01:00', $draft->due_at);
        $this->assertSame(TaskPriorityEnum::High, $draft->priority);
        $this->assertSame('c1', $draft->tenant_id);
        $this->assertSame('m1', $draft->assigned_membership_id);

        Http::assertSent(function (Request $request): bool {
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

            return $request['model'] === 'claude-sonnet-5-5'
                && str_contains($body, '2026-11-02')
                && str_contains($body, 'Europe/Bratislava')
                && str_contains($body, 'Jana Nováková')
                && str_contains($body, 'assignee_member_id')
                && str_contains($body, 'untrusted')
                && str_contains($body, 'zavolať klientovi zajtra o 9');
        });
    }

    public function test_unknown_priority_falls_back_to_normal_and_blank_fields_become_null(): void
    {
        $this->fakeProvider(['title' => 'T', 'description' => '  ', 'due_at' => null, 'priority' => 'urgent!!', 'company_id' => null, 'assignee_member_id' => null, 'assignee_hint' => null]);

        $draft = app(LaravelAiTaskExtractor::class)->extract('x', $this->context(null));

        $this->assertSame(TaskPriorityEnum::Normal, $draft->priority);
        $this->assertNull($draft->description);
        $this->assertNull($draft->due_at);
    }

    public function test_the_whole_parse_endpoint_with_the_real_extractor_normalises_tomorrow_nine_to_utc(): void
    {
        $this->seedPermissionCatalog();
        $alpha = $this->firm('Alpha');
        $user = $this->member($alpha, 'Admin');
        $this->fakeProvider([
            'title' => 'Zavolať klientovi', 'description' => null, 'due_at' => '2026-11-03T09:00:00+01:00', 'priority' => 'normal',
            'company_id' => null, 'assignee_member_id' => null, 'assignee_hint' => null,
        ]);

        $this->apiAs($user, $alpha)->postJson('/api/tasks/parse', ['text' => 'vytvor úlohu zavolať klientovi zajtra o 9', 'company_id' => $alpha->id])
            ->assertOk()
            ->assertJsonPath('due_at', '2026-11-03T08:00:00+00:00')
            ->assertJsonPath('tenant_id', $alpha->id);

        $this->assertSame(0, Task::unscopedForSystem()->count());
    }

    public function test_a_missing_key_is_unavailable_without_any_request(): void
    {
        config(['ai.providers.anthropic.key' => null]);
        Http::fake();

        $this->assertThrows(fn () => app(LaravelAiTaskExtractor::class)->extract('x', $this->context()), AiUnavailableException::class);
        Http::assertNothingSent();
    }

    public function test_provider_errors_are_unavailable(): void
    {
        config(['ai.providers.anthropic.key' => 'k']);
        Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'boom']], 500)]);

        $this->assertThrows(fn () => app(LaravelAiTaskExtractor::class)->extract('x', $this->context()), AiUnavailableException::class);
    }

    public function test_timeouts_are_unavailable(): void
    {
        config(['ai.providers.anthropic.key' => 'k']);
        Http::fake(['api.anthropic.com/*' => fn () => throw new ConnectionException('cURL error 28')]);

        $this->assertThrows(fn () => app(LaravelAiTaskExtractor::class)->extract('x', $this->context()), AiUnavailableException::class);
    }

    public function test_any_throwable_from_the_provider_layer_is_unavailable(): void
    {
        config(['ai.providers.anthropic.key' => 'k']);
        Http::fake(['api.anthropic.com/*' => fn () => throw new LogicException('unexpected')]);

        $this->assertThrows(fn () => app(LaravelAiTaskExtractor::class)->extract('x', $this->context()), AiUnavailableException::class);
    }
}
