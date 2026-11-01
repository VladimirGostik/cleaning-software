<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\ActionChannelEnum;
use App\Enums\PendingActionEnum;
use App\Enums\TaskCommentAuthorEnum;
use App\Enums\TaskRunOutcomeEnum;
use App\Enums\TaskRunStatusEnum;
use App\Enums\TaskStatusEnum;
use App\Events\TaskDue;
use App\Jobs\RunTaskWithAi;
use App\Models\Activity;
use App\Models\PendingAction;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskRun;
use App\Services\Ai\ActionConfirmationService;
use App\Services\Ai\TaskRunResult;
use App\Services\TaskRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesTaskRunFixtures;
use Tests\TestCase;

final class TaskRunExecutionTest extends TestCase
{
    use CreatesTaskRunFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTaskRunFixtures();
    }

    private function aiCommentOf(TaskRun $run): TaskComment
    {
        return TaskComment::query()->withoutGlobalScopes()->where('task_run_id', $run->id)->sole();
    }

    private function pendingFor(TaskRun $run, bool $expired = false): PendingAction
    {
        return PendingAction::factory()->state([
            'tenant_id' => $run->tenant_id,
            'user_id' => $run->started_by_user_id,
            'task_run_id' => $run->id,
            'channel' => ActionChannelEnum::TaskRun,
            'token_hash' => null,
            'expires_at' => $expired ? now()->subMinute() : now()->addHours(24),
        ])->create();
    }

    public function test_a_clean_done_closes_the_task_once_and_says_so(): void
    {
        Event::fake([TaskDue::class]);
        $task = $this->firmTask();
        $task->update(['remind_at' => now()->subHour()]);
        $run = $this->queuedRun($task, $this->admin);
        $this->runner->result = new TaskRunResult(TaskRunOutcomeEnum::Done, 'Quote drafted.');

        $run = $this->execute($run);

        $task->refresh();
        $this->assertSame(TaskRunStatusEnum::Done, $run->status);
        $this->assertTrue($run->closed_task);
        $this->assertNotNull($run->finished_at);
        $this->assertSame(TaskStatusEnum::Done, $task->status);
        $this->assertNotNull($task->completed_at);
        $comment = $this->aiCommentOf($run);
        $this->assertSame(TaskCommentAuthorEnum::Ai, $comment->author_kind);
        $this->assertSame($this->admin->id, $comment->user_id);
        $this->assertStringContainsString('Quote drafted.', $comment->body);
        $this->assertStringContainsString($this->notice('task_run_notice_closed'), $comment->body);
        $audit = Activity::query()->where('description', 'tool.task_run_close')->sole();
        $this->assertSame('task_run', $audit->getProperty('channel'));
        $this->assertSame($this->admin->id, $audit->causer_id);
        $this->assertSame(['run_id' => $run->id], $audit->getProperty('arguments'));

        $this->artisanCommand('app:remind-tasks')->assertExitCode(0);
        Event::assertNotDispatched(TaskDue::class);

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/reopen")->assertOk();
        $this->assertSame(TaskStatusEnum::Open, $task->refresh()->status);
    }

    public function test_the_model_call_sees_only_the_run_company_and_runs_as_the_starter(): void
    {
        $task = $this->firmTask();
        $seen = [];
        $this->runner->whileRunning = function () use (&$seen): void {
            $seen = [app('current_tenant_id'), auth()->id(), app(PermissionRegistrar::class)->getPermissionsTeamId()];
        };

        $this->execute($this->queuedRun($task, $this->admin));

        $this->assertSame([$this->alpha->id, $this->admin->id, $this->alpha->id], $seen);
        $this->assertFalse(app()->bound('current_tenant_id'));
        $this->assertNull(auth()->id());
        $this->assertNull(app(PermissionRegistrar::class)->getPermissionsTeamId());
    }

    public function test_worker_state_is_restored_when_the_runner_throws(): void
    {
        $task = $this->firmTask();
        $run = $this->queuedRun($task, $this->admin);
        $this->runner->whileRunning = static fn () => throw new RuntimeException('boom');
        $this->consoleContext();

        try {
            app(TaskRunService::class)->execute($run->id);
            $this->fail('Expected the runtime exception.');
        } catch (RuntimeException) {
            $this->assertFalse(app()->bound('current_tenant_id'));
            $this->assertNull(auth()->id());
        }

        app(TaskRunService::class)->markFailed($run->id);
        $this->assertSame(TaskRunStatusEnum::Failed, $run->refresh()->status);
        $this->assertStringContainsString($this->notice('task_run_notice_lost'), $this->aiCommentOf($run)->body);
    }

    public function test_done_with_a_prepared_action_waits_for_confirmation_and_keeps_the_task_open(): void
    {
        $task = $this->firmTask();
        $run = $this->queuedRun($task, $this->admin);
        $pending = $this->pendingFor($run);
        $this->runner->result = new TaskRunResult(TaskRunOutcomeEnum::Done, 'Invoice prepared.');

        $run = $this->execute($run);

        $this->assertSame(TaskRunStatusEnum::AwaitingConfirmation, $run->status);
        $this->assertFalse($run->closed_task);
        $this->assertSame(TaskStatusEnum::Open, $task->refresh()->status);
        $this->assertSame($run->id, $pending->refresh()->task_run_id);
        $this->assertStringContainsString($this->notice('task_run_notice_awaiting'), $this->aiCommentOf($run)->body);
    }

    public function test_an_unexpired_action_of_an_earlier_run_blocks_closing_and_an_expired_one_does_not(): void
    {
        $task = $this->firmTask();
        $earlier = $this->finishedRun($task, $this->admin, TaskRunStatusEnum::AwaitingConfirmation);
        $this->aiComment($task, $earlier);
        $this->pendingFor($earlier);

        $blocked = $this->execute($this->queuedRun($task, $this->admin));
        $this->assertSame(TaskRunStatusEnum::AwaitingConfirmation, $blocked->status);

        PendingAction::query()->update(['expires_at' => now()->subMinute()]);
        $closed = $this->execute($this->queuedRun($task, $this->admin));
        $this->assertSame(TaskRunStatusEnum::Done, $closed->status);
        $this->assertTrue($closed->closed_task);
    }

    public function test_done_after_a_refused_write_could_not_finish_and_leaves_the_task_open(): void
    {
        $task = $this->firmTask();
        $this->runner->result = new TaskRunResult(TaskRunOutcomeEnum::Done, 'Done.', refusedWrites: 1);

        $run = $this->execute($this->queuedRun($task, $this->admin));

        $this->assertSame(TaskRunStatusEnum::CouldNotFinish, $run->status);
        $this->assertSame(TaskStatusEnum::Open, $task->refresh()->status);
        $this->assertStringContainsString($this->notice('task_run_notice_writes_refused'), $this->aiCommentOf($run)->body);
    }

    public function test_a_starter_who_may_not_edit_the_task_gets_done_without_closing(): void
    {
        $starter = $this->member($this->alpha, 'Interná upratovačka');
        $this->grant($starter, $this->alpha, 'view tasks', 'run tasks with ai');
        $task = $this->firmTask($starter);

        $run = $this->execute($this->queuedRun($task, $starter));

        $this->assertSame(TaskRunStatusEnum::Done, $run->status);
        $this->assertFalse($run->closed_task);
        $this->assertSame(TaskStatusEnum::Open, $task->refresh()->status);
        $this->assertStringContainsString($this->notice('task_run_notice_not_closed'), $this->aiCommentOf($run)->body);
    }

    public function test_a_task_closed_meanwhile_is_not_an_error(): void
    {
        $task = $this->firmTask();
        $this->runner->whileRunning = function () use ($task): void {
            Task::unscopedForSystem()->whereKey($task->id)->update(['status' => TaskStatusEnum::Done->value, 'completed_at' => now()]);
        };

        $run = $this->execute($this->queuedRun($task, $this->admin));

        $this->assertSame(TaskRunStatusEnum::Done, $run->status);
        $this->assertFalse($run->closed_task);
    }

    public function test_question_cannot_no_report_and_provider_failure_leave_the_task_untouched(): void
    {
        $cases = [
            [new TaskRunResult(TaskRunOutcomeEnum::Question, 'Which client?'), TaskRunStatusEnum::NeedsInput, 'Which client?'],
            [new TaskRunResult(TaskRunOutcomeEnum::Cannot, 'No access to bank data.'), TaskRunStatusEnum::CouldNotFinish, 'No access to bank data.'],
            [new TaskRunResult(message: 'I looked around.'), TaskRunStatusEnum::CouldNotFinish, 'I looked around.'],
            [new TaskRunResult, TaskRunStatusEnum::CouldNotFinish, $this->notice('task_run_notice_no_report')],
            [TaskRunResult::providerFailed(), TaskRunStatusEnum::Failed, $this->notice('task_run_notice_failed')],
            [new TaskRunResult(TaskRunOutcomeEnum::Done, 'Secret model text', restricted: true), TaskRunStatusEnum::Blocked, $this->notice('task_run_notice_blocked')],
        ];

        foreach ($cases as [$result, $status, $text]) {
            $task = $this->firmTask();
            $before = $task->refresh()->getAttributes();
            $this->runner->result = $result;

            $run = $this->execute($this->queuedRun($task, $this->admin));

            $this->assertSame($status, $run->status, $status->value);
            $this->assertFalse($run->closed_task);
            $this->assertSame($before, Task::unscopedForSystem()->findOrFail($task->id)->getAttributes(), $status->value);
            $body = $this->aiCommentOf($run)->body;
            $this->assertStringContainsString($text, $body);
            $this->assertStringNotContainsString('Secret model text', $body);
        }
    }

    public function test_an_unavailable_provider_fails_the_run_with_a_comment(): void
    {
        $this->runner->throws = true;

        $run = $this->execute($this->queuedRun($this->firmTask(), $this->admin));

        $this->assertSame(TaskRunStatusEnum::Failed, $run->status);
        $this->assertSame(1, TaskComment::query()->withoutGlobalScopes()->where('task_run_id', $run->id)->count());
    }

    public function test_a_starter_who_lost_the_membership_or_the_permission_gets_no_model_call(): void
    {
        $lost = $this->member($this->alpha, 'Admin');
        $task = $this->firmTask($lost);
        $run = $this->queuedRun($task, $lost);
        $this->membershipOf($lost, $this->alpha)->update(['is_active' => false]);

        $this->assertSame(TaskRunStatusEnum::Failed, $this->execute($run)->status);
        $this->assertSame([], $this->runner->requests);
        $this->assertStringContainsString($this->notice('task_run_notice_not_allowed'), $this->aiCommentOf($run)->body);

        $demoted = $this->member($this->alpha, 'Vedúca');
        $second = $this->queuedRun($this->firmTask($demoted), $demoted);
        $this->grant($demoted, $this->alpha, 'view tasks');
        $demoted->revokePermissionTo('run tasks with ai');
        $demoted->roles()->detach();

        $this->assertSame(TaskRunStatusEnum::Failed, $this->execute($second)->status);
        $this->assertSame([], $this->runner->requests);
    }

    public function test_a_soft_deleted_task_fails_the_run_without_throwing(): void
    {
        $task = $this->firmTask();
        $run = $this->queuedRun($task, $this->admin);
        $task->delete();

        $this->assertSame(TaskRunStatusEnum::Failed, $this->execute($run)->status);
        $this->assertSame([], $this->runner->requests);
        $this->assertStringContainsString($this->notice('task_run_notice_task_gone'), $this->aiCommentOf($run)->body);
    }

    public function test_a_run_that_is_no_longer_queued_is_a_no_op(): void
    {
        $task = $this->firmTask();
        $run = $this->finishedRun($task, $this->admin, TaskRunStatusEnum::Done);

        $this->execute($run);

        $this->assertSame([], $this->runner->requests);
        $this->assertSame(0, TaskComment::query()->withoutGlobalScopes()->count());
    }

    public function test_the_stale_sweep_fails_stuck_runs_once_and_the_task_can_start_again(): void
    {
        $queuedTask = $this->firmTask();
        $runningTask = $this->firmTask();
        $freshTask = $this->firmTask();
        $queued = $this->queuedRun($queuedTask, $this->admin);
        $running = $this->queuedRun($runningTask, $this->admin);
        $fresh = $this->queuedRun($freshTask, $this->admin);
        TaskRun::query()->whereKey($queued->id)->update(['created_at' => now()->subMinutes(20)]);
        TaskRun::query()->whereKey($running->id)->update(['status' => 'running', 'started_at' => now()->subMinutes(20)]);

        $this->artisanCommand('cleanup:task-runs')->expectsOutputToContain('Failed stale task runs: 2.')->assertExitCode(0);
        $this->artisanCommand('cleanup:task-runs')->expectsOutputToContain('Failed stale task runs: 0.')->assertExitCode(0);

        foreach ([$queued, $running] as $run) {
            $this->assertSame(TaskRunStatusEnum::Failed, $run->refresh()->status);
            $this->assertSame(1, TaskComment::query()->withoutGlobalScopes()->where('task_run_id', $run->id)->count());
        }
        $this->assertSame(TaskRunStatusEnum::Queued, $fresh->refresh()->status);

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$queuedTask->id}/ai-runs")->assertStatus(202);
    }

    public function test_the_job_failure_hook_fails_the_run_idempotently(): void
    {
        $run = $this->queuedRun($this->firmTask(), $this->admin);
        $job = new RunTaskWithAi($run->id);

        $job->failed(new RuntimeException('timeout'));
        $job->failed(new RuntimeException('timeout'));

        $this->assertSame(TaskRunStatusEnum::Failed, $run->refresh()->status);
        $this->assertSame(1, TaskComment::query()->withoutGlobalScopes()->where('task_run_id', $run->id)->count());
    }

    public function test_the_context_holds_only_comments_the_starter_may_read(): void
    {
        $other = $this->member($this->alpha, 'Vedúca');
        $starter = $this->member($this->alpha, 'Sekretárka');
        $this->grant($starter, $this->alpha, 'run tasks with ai');
        $task = $this->firmTask($starter);
        $this->humanComment($task, $starter, 'Human note');
        $foreignRun = $this->finishedRun($task, $other, TaskRunStatusEnum::Done);
        $foreignRun->update(['actions' => [['type' => 'created', 'label' => 'Foreign action', 'url' => null]]]);
        $this->aiComment($task, $foreignRun, 'Text of someone else run');
        $ownRun = $this->finishedRun($task, $starter, TaskRunStatusEnum::CouldNotFinish);
        $ownRun->update(['actions' => [['type' => 'created', 'label' => 'Own action', 'url' => '/tasks/x']]]);
        $this->aiComment($task, $ownRun, 'Text of my run');

        $this->execute($this->queuedRun($task, $starter));

        $request = $this->runner->requests[0];
        $bodies = array_column($request->comments, 'body');
        $this->assertContains('Human note', $bodies);
        $this->assertContains('Text of my run', $bodies);
        $this->assertNotContains('Text of someone else run', $bodies);
        $this->assertSame(['Own action'], array_map(static fn ($action): string => $action->label, $request->priorActions));

        $viewAll = $this->execute($this->queuedRun($task, $other));
        $this->assertContains('Text of someone else run', array_column($this->runner->requests[1]->comments, 'body'));
        $this->assertSame(TaskRunStatusEnum::Done, $viewAll->status);
    }

    public function test_the_context_is_bounded_and_counts_omitted_comments(): void
    {
        $task = $this->firmTask();
        TaskComment::factory()->count(40)->forTask($task)->by($this->admin)->create();

        $this->execute($this->queuedRun($task, $this->admin));

        $request = $this->runner->requests[0];
        $this->assertCount(30, $request->comments);
        $this->assertSame(10, $request->omittedComments);
    }

    public function test_comments_and_runs_do_not_cancel_a_pending_delete_task_but_the_auto_close_does(): void
    {
        $task = $this->firmTask();
        $pending = app(ActionConfirmationService::class)->prepare($this->admin, $this->alpha->id, PendingActionEnum::DeleteTask, $task, [], ActionChannelEnum::Assistant);

        $this->humanComment($task, $this->admin);
        $this->apiAs($this->admin, $this->alpha)->getJson("/api/tasks/{$task->id}/thread")->assertOk();
        $this->apiAs($this->admin, $this->alpha)->getJson('/api/tasks')->assertOk();
        $this->runner->result = new TaskRunResult(TaskRunOutcomeEnum::Question, 'Which client?');
        $this->execute($this->queuedRun($task, $this->admin));

        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $pending->pending_action->id))->assertRedirect();
        $this->assertNull(Task::unscopedForSystem()->find($task->id));

        $closing = $this->firmTask();
        $second = app(ActionConfirmationService::class)->prepare($this->admin, $this->alpha->id, PendingActionEnum::DeleteTask, $closing, [], ActionChannelEnum::Assistant);
        $this->runner->result = new TaskRunResult(TaskRunOutcomeEnum::Done, 'Done.');
        $this->assertTrue($this->execute($this->queuedRun($closing, $this->admin))->closed_task);

        $this->webAs($this->admin, $this->alpha)->post(route('pending-actions.confirm', $second->pending_action->id))
            ->assertSessionHasErrors(['status' => __('app.pending_action_subject_changed')]);
        $this->assertNotNull(Task::unscopedForSystem()->find($closing->id));
    }
}
