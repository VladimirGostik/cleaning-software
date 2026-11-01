<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\TaskRunOutcomeEnum;
use App\Enums\TaskRunStatusEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Activity;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskRun;
use App\Services\Ai\TaskRunResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTaskRunFixtures;
use Tests\TestCase;

final class TaskRunGlobalTaskTest extends TestCase
{
    use CreatesTaskRunFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTaskRunFixtures();
    }

    public function test_a_personal_task_needs_a_company_and_the_thread_lists_the_choices(): void
    {
        $task = $this->globalTask();
        $client = $this->apiAs($this->admin, $this->alpha);

        $client->postJson("/api/tasks/{$task->id}/ai-runs")->assertUnprocessable()->assertJsonValidationErrors('company_id');
        $thread = $client->getJson("/api/tasks/{$task->id}/thread")->assertOk()->assertJsonPath('can_start_run', true);
        $this->assertEqualsCanonicalizing([$this->alpha->id, $this->beta->id], array_column((array) $thread->json('run_companies'), 'id'));
    }

    public function test_a_company_without_membership_or_without_the_permission_is_forbidden(): void
    {
        $task = $this->globalTask();
        $outside = $this->firm('Gamma');
        $limited = $this->member($this->alpha, 'Sekretárka');
        $limitedTask = $this->globalTask($limited);

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/ai-runs", ['company_id' => $outside->id])->assertForbidden();
        $this->apiAs($limited, $this->alpha)->postJson("/api/tasks/{$limitedTask->id}/ai-runs", ['company_id' => $this->alpha->id])->assertForbidden();
        $this->apiAs($limited, $this->alpha)->getJson("/api/tasks/{$limitedTask->id}/thread")->assertJsonPath('run_companies', [])->assertJsonPath('can_start_run', false);
        $this->assertSame(0, TaskRun::query()->count());
    }

    public function test_another_users_personal_task_is_not_found(): void
    {
        $other = $this->member($this->alpha, 'Admin');
        $task = $this->globalTask($other);

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/ai-runs", ['company_id' => $this->alpha->id])->assertNotFound();
    }

    public function test_the_run_acts_in_the_chosen_company_while_the_request_is_bound_to_another(): void
    {
        $task = $this->globalTask();
        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/ai-runs", ['company_id' => $this->beta->id])
            ->assertStatus(202)
            ->assertJsonPath('latest_run.tenant_id', $this->beta->id)
            ->assertJsonPath('latest_run.tenant_name', 'Beta');
        $run = TaskRun::query()->withoutGlobalScopes()->sole();
        $seen = null;
        $this->runner->whileRunning = function () use (&$seen): void {
            $seen = app('current_tenant_id');
        };

        $this->execute($run);

        $this->assertSame($this->beta->id, $seen);
        $this->assertTrue($this->runner->requests[0]->isGlobal);
        $this->assertSame($this->beta->id, $this->runner->requests[0]->companyId);
    }

    public function test_the_run_comment_has_no_company_the_task_closes_and_activity_carries_no_task_data(): void
    {
        $task = $this->globalTask();
        $run = $this->queuedRun($task, $this->admin, $this->beta);
        $this->runner->result = new TaskRunResult(TaskRunOutcomeEnum::Done, 'Done for you.');

        $run = $this->execute($run);

        $comment = TaskComment::query()->withoutGlobalScopes()->where('task_run_id', $run->id)->sole();
        $this->assertNull($comment->tenant_id);
        $this->assertSame(TaskRunStatusEnum::Done, $run->status);
        $this->assertTrue($run->closed_task);
        $this->assertSame(TaskStatusEnum::Done, Task::unscopedForSystem()->findOrFail($task->id)->status);
        $this->assertNull(Activity::query()->where('subject_type', $comment->getMorphClass())->where('subject_id', $comment->id)->sole()->tenant_id);
        $this->assertSame(0, Activity::query()->where('subject_type', $run->getMorphClass())->count());
    }

    public function test_the_thread_of_a_personal_task_shows_the_run_to_its_creator_only(): void
    {
        $task = $this->globalTask();
        $run = $this->finishedRun($task, $this->admin, TaskRunStatusEnum::Done, $this->beta);
        $this->aiComment($task, $run, 'Personal AI note');
        $other = $this->member($this->alpha, 'Admin');

        $this->apiAs($this->admin, $this->alpha)->getJson("/api/tasks/{$task->id}/thread")
            ->assertJsonPath('latest_run.tenant_name', 'Beta')
            ->assertJsonCount(1, 'comments');
        $this->apiAs($other, $this->alpha)->getJson("/api/tasks/{$task->id}/thread")->assertNotFound();
        $this->assertSame('done', $this->apiAs($this->admin, $this->beta)->getJson('/api/tasks?scope=all')->json('data.0.ai_run_status'));
    }
}
