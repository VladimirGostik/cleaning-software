<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Data\Tasks\TaskRunStartData;
use App\Enums\TaskRunStatusEnum;
use App\Jobs\RunTaskWithAi;
use App\Models\TaskComment;
use App\Models\TaskRun;
use App\Services\TaskRunService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesTaskRunFixtures;
use Tests\TestCase;

final class TaskRunStartTest extends TestCase
{
    use CreatesTaskRunFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTaskRunFixtures();
    }

    public function test_a_permitted_person_queues_one_run_on_an_open_firm_task(): void
    {
        $task = $this->firmTask();

        $this->apiAs($this->admin, $this->alpha)
            ->postJson("/api/tasks/{$task->id}/ai-runs")
            ->assertStatus(202)
            ->assertJsonPath('latest_run.status', 'queued')
            ->assertJsonPath('latest_run.is_active', true)
            ->assertJsonPath('can_start_run', false);

        $run = TaskRun::query()->sole();
        $this->assertSame($this->alpha->id, $run->tenant_id);
        $this->assertSame($this->admin->id, $run->started_by_user_id);
        Queue::assertPushedOn('ai', RunTaskWithAi::class, fn (RunTaskWithAi $job): bool => $job->runId === $run->id);
    }

    public function test_the_web_start_redirects_back_and_queues(): void
    {
        $task = $this->firmTask();

        $this->webAs($this->admin, $this->alpha)->post(route('tasks.ai-runs.store', $task))->assertRedirect();

        $this->assertSame(1, TaskRun::query()->count());
        Queue::assertPushed(RunTaskWithAi::class);
    }

    public function test_without_the_permission_it_is_forbidden_and_nothing_is_queued(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');
        $task = $this->firmTask($secretary);

        $this->apiAs($secretary, $this->alpha)->postJson("/api/tasks/{$task->id}/ai-runs")->assertForbidden();

        $this->assertSame(0, TaskRun::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_closed_task_and_a_second_start_while_active_are_status_errors(): void
    {
        $closed = $this->firmTask();
        $closed->update(['status' => 'done']);
        $open = $this->firmTask();
        $client = $this->apiAs($this->admin, $this->alpha);

        $client->postJson("/api/tasks/{$closed->id}/ai-runs")->assertUnprocessable()->assertJsonValidationErrors('status');
        $client->postJson("/api/tasks/{$open->id}/ai-runs")->assertStatus(202);
        $client->postJson("/api/tasks/{$open->id}/ai-runs")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame(1, TaskRun::query()->count());
    }

    public function test_the_unique_index_turns_a_concurrent_second_insert_into_a_status_error(): void
    {
        $task = $this->firmTask();
        $this->queuedRun($task, $this->admin);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->queuedRun($task, $this->admin);
    }

    public function test_a_start_that_loses_the_race_reports_status_not_a_server_error(): void
    {
        $task = $this->firmTask();
        $this->queuedRun($task, $this->admin);
        $this->consoleContext();

        try {
            $this->actingAs($this->admin);
            $this->bindTenant($this->alpha);
            app(TaskRunService::class)->start($task, new TaskRunStartData, $this->admin);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    public function test_without_a_key_nothing_is_queued_and_the_thread_says_so(): void
    {
        $task = $this->firmTask();
        $this->runner->available = false;
        $client = $this->apiAs($this->admin, $this->alpha);

        $client->postJson("/api/tasks/{$task->id}/ai-runs")->assertUnprocessable()->assertJsonValidationErrors('ai');
        $client->getJson("/api/tasks/{$task->id}/thread")->assertJsonPath('ai_available', false);

        $this->assertSame(0, TaskRun::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_plain_comment_or_an_answer_never_creates_a_run(): void
    {
        $task = $this->firmTask();
        $run = $this->finishedRun($task, $this->admin, TaskRunStatusEnum::NeedsInput);
        $question = $this->aiComment($task, $run, 'Which client?');
        $client = $this->apiAs($this->admin, $this->alpha);

        $client->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Just a note'])->assertCreated();
        $client->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Alfa', 'answers_comment_id' => $question->id])->assertCreated();

        $this->assertSame(1, TaskRun::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_an_answer_needs_the_current_open_question_and_a_deleted_answer_frees_it(): void
    {
        $task = $this->firmTask();
        $old = $this->finishedRun($task, $this->admin, TaskRunStatusEnum::Done);
        $oldQuestion = $this->aiComment($task, $old, 'Old question');
        $run = $this->finishedRun($task, $this->admin, TaskRunStatusEnum::NeedsInput);
        $question = $this->aiComment($task, $run, 'Which client?');
        $plain = $this->humanComment($task, $this->admin);
        $client = $this->apiAs($this->admin, $this->alpha);

        $client->postJson("/api/tasks/{$task->id}/comments", ['body' => 'a', 'answers_comment_id' => $oldQuestion->id])->assertUnprocessable()->assertJsonValidationErrors('answers_comment_id');
        $client->postJson("/api/tasks/{$task->id}/comments", ['body' => 'a', 'answers_comment_id' => $plain->id])->assertUnprocessable()->assertJsonValidationErrors('answers_comment_id');

        $first = $client->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Alfa', 'answers_comment_id' => $question->id])->assertCreated();
        $client->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Beta', 'answers_comment_id' => $question->id])->assertUnprocessable()->assertJsonValidationErrors('answers_comment_id');

        $answerId = $this->rowValue($first->json('comments'), 'body', 'Alfa', 'id');
        $this->assertIsString($answerId);
        $client->deleteJson("/api/tasks/{$task->id}/comments/{$answerId}")->assertOk()->assertJsonPath('question.can_answer', true);
        $client->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Gamma', 'answers_comment_id' => $question->id])->assertCreated();
    }

    public function test_an_answer_to_a_closed_task_is_a_status_error_and_an_outsider_cannot_answer(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');
        $task = $this->firmTask($secretary);
        $run = $this->finishedRun($task, $this->admin, TaskRunStatusEnum::NeedsInput);
        $question = $this->aiComment($task, $run, 'Which client?');

        $this->apiAs($secretary, $this->alpha)->postJson("/api/tasks/{$task->id}/comments", ['body' => 'a', 'answers_comment_id' => $question->id])
            ->assertUnprocessable()->assertJsonValidationErrors('answers_comment_id');

        $task->update(['status' => 'done']);
        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/comments", ['body' => 'a', 'answers_comment_id' => $question->id])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame(0, TaskComment::query()->where('answers_comment_id', $question->id)->count());
    }

    public function test_continuing_after_an_answer_is_a_new_run_by_whoever_presses_the_button(): void
    {
        $task = $this->firmTask();
        $other = $this->member($this->alpha, 'Vedúca');
        $run = $this->finishedRun($task, $this->admin, TaskRunStatusEnum::NeedsInput);
        $question = $this->aiComment($task, $run, 'Which client?');
        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Alfa', 'answers_comment_id' => $question->id])->assertCreated();

        $this->apiAs($other, $this->alpha)->postJson("/api/tasks/{$task->id}/ai-runs")->assertStatus(202);

        $this->assertSame(2, TaskRun::query()->count());
        $this->assertSame($other->id, TaskRun::query()->where('status', TaskRunStatusEnum::Queued->value)->sole()->started_by_user_id);
    }
}
