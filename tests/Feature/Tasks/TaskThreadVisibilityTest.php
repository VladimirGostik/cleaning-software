<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\ActionChannelEnum;
use App\Enums\TaskRunStatusEnum;
use App\Models\PendingAction;
use App\Models\Task;
use App\Models\TaskRun;
use App\Models\User;
use App\Services\Ai\ToolContext;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesTaskRunFixtures;
use Tests\TestCase;

final class TaskThreadVisibilityTest extends TestCase
{
    use CreatesTaskRunFixtures;
    use RefreshDatabase;

    private User $creator;

    private User $assignee;

    private User $viewAllHolder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTaskRunFixtures();
        $this->creator = $this->member($this->alpha, 'Sekretárka');
        $this->assignee = $this->member($this->alpha, 'Sekretárka');
        $this->viewAllHolder = $this->member($this->alpha, 'Vedúca');
    }

    /** @return array{0: Task, 1: TaskRun} */
    private function taskWithQuestion(): array
    {
        $task = $this->firmTask($this->creator);
        $task->update(['assigned_membership_id' => $this->membershipOf($this->assignee, $this->alpha)->id]);
        $this->humanComment($task, $this->creator, 'Please prepare the quote');
        $run = $this->finishedRun($task, $this->admin, TaskRunStatusEnum::NeedsInput);
        $this->aiComment($task, $run, 'Which client?');

        return [$task, $run];
    }

    public function test_the_starter_and_a_view_all_holder_see_ai_output_while_creator_and_assignee_see_only_human_comments(): void
    {
        [$task] = $this->taskWithQuestion();

        foreach ([$this->admin, $this->viewAllHolder] as $audience) {
            $thread = $this->apiAs($audience, $this->alpha)->getJson("/api/tasks/{$task->id}/thread")->assertOk();
            $thread->assertJsonCount(2, 'comments')->assertJsonPath('latest_run.status', 'needs_input');
            $this->assertNotNull($thread->json('question'));
        }

        foreach ([$this->creator, $this->assignee] as $outsider) {
            $this->apiAs($outsider, $this->alpha)->getJson("/api/tasks/{$task->id}/thread")
                ->assertOk()
                ->assertJsonCount(1, 'comments')
                ->assertJsonPath('comments.0.author_kind', 'user')
                ->assertJsonPath('latest_run', null)
                ->assertJsonPath('question', null);
        }
    }

    public function test_the_web_prop_follows_the_same_rule(): void
    {
        [$task] = $this->taskWithQuestion();

        $this->webAs($this->creator, $this->alpha)->withoutVite()->get(route('tasks.show', $task))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('thread.comments', 1)
                ->where('thread.latest_run', null)
                ->where('thread.question', null));

        $this->webAs($this->admin, $this->alpha)->withoutVite()->get(route('tasks.show', $task))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('thread.comments', 2)
                ->where('thread.latest_run.status', 'needs_input'));
    }

    public function test_the_list_status_is_null_outside_the_audience_on_both_lists_and_in_list_tasks(): void
    {
        [$task] = $this->taskWithQuestion();

        foreach (['company', 'all'] as $scope) {
            $this->assertSame('needs_input', $this->apiAs($this->admin, $this->alpha)->getJson("/api/tasks?scope={$scope}")->assertOk()->json('data.0.ai_run_status'), $scope);
            $this->assertSame('needs_input', $this->apiAs($this->viewAllHolder, $this->alpha)->getJson("/api/tasks?scope={$scope}")->json('data.0.ai_run_status'), $scope);
            $this->assertNull($this->apiAs($this->creator, $this->alpha)->getJson("/api/tasks?scope={$scope}")->assertOk()->json('data.0.ai_run_status'), $scope);
        }

        $this->webAs($this->creator, $this->alpha)->withoutVite()->get(route('tasks.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tasks.data.0.ai_run_status', null));

        $this->consoleContext();
        $listed = app(ToolContext::class)->run($this->creator, $this->alpha->id, fn () => app(TaskService::class)->paginate(request(), $this->creator)->items());
        $this->assertNull($listed[0]->ai_run_status);
        $this->assertSame($task->id, $listed[0]->id);
    }

    public function test_the_list_status_is_not_leaked_on_the_across_companies_list_for_a_non_view_all_member(): void
    {
        $task = $this->firmTask($this->creator, $this->alpha);
        $this->finishedRun($task, $this->admin, TaskRunStatusEnum::Done);
        $this->member($this->beta, 'Sekretárka', $this->creator);
        $betaTask = $this->firmTask($this->creator, $this->beta);
        $this->finishedRun($betaTask, $this->creator, TaskRunStatusEnum::Failed);

        $rows = $this->apiAs($this->creator, $this->alpha)->getJson('/api/tasks?scope=all')->assertOk()->json('data');

        $this->assertNull($this->rowValue($rows, 'id', $task->id, 'ai_run_status'));
        $this->assertSame('failed', $this->rowValue($rows, 'id', $betaTask->id, 'ai_run_status'));
    }

    public function test_a_hidden_ai_comment_cannot_be_deleted_and_is_a_404(): void
    {
        [$task, $run] = $this->taskWithQuestion();
        $aiComment = $run->comment()->firstOrFail();

        $this->apiAs($this->creator, $this->alpha)->deleteJson("/api/tasks/{$task->id}/comments/{$aiComment->id}")->assertNotFound();
        $this->assertNotSoftDeleted($aiComment);
        $this->apiAs($this->viewAllHolder, $this->alpha)->deleteJson("/api/tasks/{$task->id}/comments/{$aiComment->id}")->assertForbidden();
        $this->apiAs($this->admin, $this->alpha)->deleteJson("/api/tasks/{$task->id}/comments/{$aiComment->id}")->assertOk();
    }

    public function test_an_answer_hides_its_link_from_a_viewer_who_cannot_see_the_question(): void
    {
        [$task, $run] = $this->taskWithQuestion();
        $question = $run->comment()->firstOrFail();

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Client Alfa', 'answers_comment_id' => $question->id])->assertCreated();

        $creatorView = $this->apiAs($this->creator, $this->alpha)->getJson("/api/tasks/{$task->id}/thread")->assertOk();
        $this->assertNull($this->rowValue($creatorView->json('comments'), 'body', 'Client Alfa', 'answers_comment_id'));

        $adminView = $this->apiAs($this->admin, $this->alpha)->getJson("/api/tasks/{$task->id}/thread")->assertOk();
        $this->assertSame($question->id, $this->rowValue($adminView->json('comments'), 'body', 'Client Alfa', 'answers_comment_id'));
        $adminView->assertJsonPath('question.answered', true)->assertJsonPath('question.can_answer', false);
    }

    public function test_a_pending_action_shows_only_to_its_owner(): void
    {
        [$task, $run] = $this->taskWithQuestion();
        $run->update(['status' => TaskRunStatusEnum::AwaitingConfirmation]);
        PendingAction::factory()->state([
            'tenant_id' => $this->alpha->id,
            'user_id' => $this->admin->id,
            'task_run_id' => $run->id,
            'channel' => ActionChannelEnum::TaskRun,
            'token_hash' => null,
            'expires_at' => now()->addHours(24),
        ])->create();

        $this->apiAs($this->admin, $this->alpha)->getJson("/api/tasks/{$task->id}/thread")->assertJsonCount(1, 'pending_actions')->assertJsonPath('pending_actions.0.tenant_name', 'Alpha');
        $this->apiAs($this->viewAllHolder, $this->alpha)->getJson("/api/tasks/{$task->id}/thread")->assertJsonCount(0, 'pending_actions');
    }
}
