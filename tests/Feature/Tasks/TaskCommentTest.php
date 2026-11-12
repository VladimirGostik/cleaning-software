<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\TaskCommentAuthorEnum;
use App\Enums\TaskRunStatusEnum;
use App\Models\Activity;
use App\Models\TaskComment;
use App\Notifications\TaskCommentedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreatesTaskRunFixtures;
use Tests\TestCase;

final class TaskCommentTest extends TestCase
{
    use CreatesTaskRunFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTaskRunFixtures();
    }

    public function test_a_viewer_without_the_run_permission_can_comment_but_not_start(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');
        $task = $this->firmTask($secretary);

        $this->webAs($secretary, $this->alpha)
            ->post(route('tasks.comments.store', $task), ['body' => 'Hello'])
            ->assertRedirect();
        $this->webAs($secretary, $this->alpha)
            ->post(route('tasks.ai-runs.store', $task), [])
            ->assertForbidden();

        $comment = TaskComment::query()->sole();
        $this->assertSame(TaskCommentAuthorEnum::User, $comment->author_kind);
        $this->assertSame($this->alpha->id, $comment->tenant_id);
        $this->assertSame($secretary->id, $comment->user_id);
    }

    public function test_the_api_returns_the_thread_with_the_new_comment(): void
    {
        $task = $this->firmTask();

        $this->apiAs($this->admin, $this->alpha)
            ->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Mobile hello'])
            ->assertCreated()
            ->assertJsonPath('comments.0.body', 'Mobile hello')
            ->assertJsonPath('comments.0.is_mine', true)
            ->assertJsonPath('comments.0.author_kind', 'user')
            ->assertJsonPath('can_comment', true)
            ->assertJsonPath('max_body_chars', TaskComment::MAX_BODY_CHARS);
    }

    public function test_body_is_required_and_bounded(): void
    {
        $task = $this->firmTask();
        $client = $this->apiAs($this->admin, $this->alpha);

        $client->postJson("/api/tasks/{$task->id}/comments", ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
        $client->postJson("/api/tasks/{$task->id}/comments", ['body' => str_repeat('a', TaskComment::MAX_BODY_CHARS + 1)])->assertUnprocessable()->assertJsonValidationErrors('body');
        $client->postJson("/api/tasks/{$task->id}/comments", ['body' => str_repeat('a', TaskComment::MAX_BODY_CHARS)])->assertCreated();
    }

    public function test_the_comment_limit_is_enforced(): void
    {
        $task = $this->firmTask();
        TaskComment::factory()->count(TaskComment::MAX_PER_TASK)->forTask($task)->by($this->admin)->create();

        $this->apiAs($this->admin, $this->alpha)
            ->postJson("/api/tasks/{$task->id}/comments", ['body' => 'one too many'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
        $this->assertSame(TaskComment::MAX_PER_TASK, TaskComment::query()->count());
    }

    public function test_a_comment_works_on_a_closed_task(): void
    {
        $task = $this->firmTask();
        $task->update(['status' => 'done']);

        $this->apiAs($this->admin, $this->alpha)
            ->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Afterthought'])
            ->assertCreated();
    }

    public function test_another_companys_task_is_not_found(): void
    {
        $betaOnly = $this->member($this->beta, 'Admin');
        $task = $this->firmTask($betaOnly, $this->beta);

        $this->apiAs($this->admin, $this->alpha)
            ->postJson("/api/tasks/{$task->id}/comments", ['body' => 'x'])
            ->assertNotFound();
        $this->apiAs($this->admin, $this->alpha)->getJson("/api/tasks/{$task->id}/thread")->assertNotFound();
    }

    public function test_comments_on_a_personal_task_have_no_company_and_no_audit_tenant_and_notify_nobody(): void
    {
        Notification::fake();
        $task = $this->globalTask();

        $this->apiAs($this->admin, $this->alpha)
            ->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Note to self'])
            ->assertCreated();

        $comment = TaskComment::query()->withoutGlobalScopes()->sole();
        $this->assertNull($comment->tenant_id);
        $activity = Activity::query()->where('subject_type', $comment->getMorphClass())->where('subject_id', $comment->id)->sole();
        $this->assertNull($activity->tenant_id);
        Notification::assertNothingSent();
        $this->apiAs($this->admin, $this->beta)->getJson("/api/tasks/{$task->id}/thread")->assertOk()->assertJsonPath('comments.0.body', 'Note to self');
    }

    public function test_another_users_personal_task_cannot_be_commented_on(): void
    {
        $other = $this->member($this->alpha, 'Admin');
        $task = $this->globalTask($other);

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/comments", ['body' => 'x'])->assertNotFound();
    }

    public function test_delete_is_for_the_author_or_a_delete_tasks_holder(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');
        $other = $this->member($this->alpha, 'Sekretárka');
        $task = $this->firmTask($secretary);
        $comment = $this->humanComment($task, $secretary);

        $this->apiAs($other, $this->alpha)->deleteJson("/api/tasks/{$task->id}/comments/{$comment->id}")->assertForbidden();
        $this->apiAs($secretary, $this->alpha)->deleteJson("/api/tasks/{$task->id}/comments/{$comment->id}")->assertOk()->assertJsonCount(0, 'comments');
        $this->assertSoftDeleted($comment);

        $second = $this->humanComment($task, $secretary, 'Another');
        $this->apiAs($this->admin, $this->alpha)->deleteJson("/api/tasks/{$task->id}/comments/{$second->id}")->assertOk();
        $this->assertSoftDeleted($second);
    }

    public function test_a_deleted_comment_leaves_the_thread_and_the_audit_log_never_holds_a_body(): void
    {
        $task = $this->firmTask();
        $comment = $this->humanComment($task, $this->admin, 'Secret wording');

        $this->apiAs($this->admin, $this->alpha)->deleteJson("/api/tasks/{$task->id}/comments/{$comment->id}")->assertOk();
        $this->apiAs($this->admin, $this->alpha)->getJson("/api/tasks/{$task->id}/thread")->assertJsonCount(0, 'comments');

        $logged = Activity::query()->where('subject_type', $comment->getMorphClass())->get();
        $this->assertNotEmpty($logged);
        $this->assertStringNotContainsString('Secret wording', (string) json_encode($logged->toArray()));
    }

    public function test_a_human_comment_notifies_creator_and_assignee_but_not_the_author(): void
    {
        Notification::fake();
        $creator = $this->member($this->alpha, 'Sekretárka');
        $assignee = $this->member($this->alpha, 'Sekretárka');
        $task = $this->firmTask($creator);
        $task->update(['assigned_membership_id' => $this->membershipOf($assignee, $this->alpha)->id]);

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/comments", ['body' => 'FYI'])->assertCreated();

        Notification::assertSentTo($creator, TaskCommentedNotification::class, function (TaskCommentedNotification $notification) use ($task): bool {
            return $notification->taskId === $task->id && ! str_contains(json_encode($notification->toArray($this->admin)) ?: '', 'FYI')
                && $notification->toArray($this->admin)['url'] === route('tasks.index', ['task' => $task->id]);
        });
        Notification::assertSentTo($assignee, TaskCommentedNotification::class);
        Notification::assertNotSentTo($this->admin, TaskCommentedNotification::class);
    }

    public function test_the_thread_endpoint_serves_the_web_session_for_polling(): void
    {
        $task = $this->firmTask();
        $this->humanComment($task, $this->admin, 'Visible');
        $this->finishedRun($task, $this->admin, TaskRunStatusEnum::Done);

        $this->webAs($this->admin, $this->alpha)
            ->getJson(route('tasks.thread', $task))
            ->assertOk()
            ->assertJsonPath('comments.0.body', 'Visible')
            ->assertJsonPath('latest_run.status', 'done');
    }
}
