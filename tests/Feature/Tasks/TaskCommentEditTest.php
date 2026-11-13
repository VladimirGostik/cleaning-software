<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\TaskRunStatusEnum;
use App\Enums\TaskStatusEnum;
use App\Events\TaskCommented;
use App\Models\Activity;
use App\Models\TaskComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesTaskRunFixtures;
use Tests\TestCase;

final class TaskCommentEditTest extends TestCase
{
    use CreatesTaskRunFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTaskRunFixtures();
    }

    public function test_the_author_edits_the_body_and_the_edit_is_marked(): void
    {
        $task = $this->firmTask();
        $comment = $this->humanComment($task, $this->admin, 'Typo');

        $this->webAs($this->admin, $this->alpha)
            ->put(route('tasks.comments.update', [$task, $comment]), ['body' => 'Fixed'])
            ->assertRedirect()
            ->assertSessionMissing('success');

        $comment->refresh();
        $this->assertSame('Fixed', $comment->body);
        $this->assertNotNull($comment->edited_at);
    }

    public function test_the_same_body_writes_nothing(): void
    {
        $task = $this->firmTask();
        $comment = $this->humanComment($task, $this->admin, 'Same');
        $rows = Activity::query()->count();

        $this->webAs($this->admin, $this->alpha)->put(route('tasks.comments.update', [$task, $comment]), ['body' => 'Same'])->assertRedirect();

        $this->assertNull($comment->refresh()->edited_at);
        $this->assertSame($rows, Activity::query()->count());
    }

    public function test_only_the_author_may_edit_even_with_delete_permission(): void
    {
        $task = $this->firmTask();
        $comment = $this->humanComment($task, $this->memberWith($this->alpha, 'Sekretárka'), 'Theirs');

        $this->webAs($this->admin, $this->alpha)->put(route('tasks.comments.update', [$task, $comment]), ['body' => 'Mine now'])->assertForbidden();

        $this->assertSame('Theirs', $comment->refresh()->body);
    }

    public function test_ai_comments_and_answers_to_a_question_are_not_editable(): void
    {
        $task = $this->firmTask();
        $run = $this->finishedRun($task, $this->admin, TaskRunStatusEnum::Done);
        $ai = $this->aiComment($task, $run);
        $answer = TaskComment::factory()->forTask($task)->by($this->admin)->state(['answers_comment_id' => $ai->id])->create();

        $this->webAs($this->admin, $this->alpha)->put(route('tasks.comments.update', [$task, $ai]), ['body' => 'x'])->assertForbidden();
        $this->webAs($this->admin, $this->alpha)->put(route('tasks.comments.update', [$task, $answer]), ['body' => 'x'])->assertForbidden();
    }

    public function test_foreign_task_comment_is_not_found(): void
    {
        $task = $this->firmTask();
        $other = $this->firmTask();
        $comment = $this->humanComment($other, $this->admin);

        $this->webAs($this->admin, $this->alpha)->put(route('tasks.comments.update', [$task, $comment]), ['body' => 'x'])->assertNotFound();
    }

    public function test_an_invisible_ai_comment_is_not_found(): void
    {
        $viewer = $this->memberWith($this->alpha, 'Sekretárka');
        $task = $this->firmTask($viewer);
        $starter = $this->memberWith($this->alpha, 'Sekretárka', 'run tasks with ai');
        $ai = $this->aiComment($task, $this->finishedRun($task, $starter, TaskRunStatusEnum::Done));

        $this->webAs($viewer, $this->alpha)->put(route('tasks.comments.update', [$task, $ai]), ['body' => 'x'])->assertNotFound();
    }

    public function test_done_and_personal_tasks_stay_editable_by_the_author(): void
    {
        $done = $this->firmTask();
        $done->update(['status' => TaskStatusEnum::Done]);
        $doneComment = $this->humanComment($done, $this->admin, 'Old');
        $personal = $this->globalTask();
        $personalComment = TaskComment::factory()->forTask($personal)->by($this->admin)->create();

        $this->webAs($this->admin, $this->alpha)->put(route('tasks.comments.update', [$done, $doneComment]), ['body' => 'New'])->assertRedirect();
        $this->webAs($this->admin, $this->alpha)->put(route('tasks.comments.update', [$personal, $personalComment]), ['body' => 'Mine'])->assertRedirect();

        $this->assertSame('New', $doneComment->refresh()->body);
        $this->assertSame('Mine', $personalComment->refresh()->body);
    }

    public function test_body_is_validated_and_other_fields_are_ignored(): void
    {
        $task = $this->firmTask();
        $comment = $this->humanComment($task, $this->admin, 'Keep');

        $this->webAs($this->admin, $this->alpha)->put(route('tasks.comments.update', [$task, $comment]), ['body' => ''])->assertSessionHasErrors('body');
        $this->webAs($this->admin, $this->alpha)->put(route('tasks.comments.update', [$task, $comment]), ['body' => str_repeat('a', TaskComment::MAX_BODY_CHARS + 1)])->assertSessionHasErrors('body');
        $this->webAs($this->admin, $this->alpha)->put(route('tasks.comments.update', [$task, $comment]), ['body' => 'Ok', 'answers_comment_id' => $comment->id])->assertRedirect();

        $comment->refresh();
        $this->assertSame('Ok', $comment->body);
        $this->assertNull($comment->answers_comment_id);
    }

    public function test_an_edit_logs_edited_at_without_the_body_and_dispatches_no_event(): void
    {
        $task = $this->firmTask();
        $comment = $this->humanComment($task, $this->admin, 'Secret before');
        Event::fake([TaskCommented::class]);
        Activity::query()->delete();

        $this->webAs($this->admin, $this->alpha)->put(route('tasks.comments.update', [$task, $comment]), ['body' => 'Secret after'])->assertRedirect();

        $row = Activity::query()->sole();
        $this->assertArrayHasKey('edited_at', (array) data_get($row->attributeChangesArray(), 'attributes'));
        $this->assertStringNotContainsString('Secret', json_encode($row->getAttributes(), JSON_THROW_ON_ERROR));
        Event::assertNotDispatched(TaskCommented::class);
    }

    public function test_the_thread_carries_can_edit_and_edited_at(): void
    {
        $task = $this->firmTask();
        $secretary = $this->memberWith($this->alpha, 'Sekretárka');
        $mine = $this->humanComment($task, $this->admin, 'Mine');
        $theirs = $this->humanComment($task, $secretary, 'Theirs');
        $ai = $this->aiComment($task, $this->finishedRun($task, $this->admin, TaskRunStatusEnum::Done));
        $answer = TaskComment::factory()->forTask($task)->by($this->admin)->state(['answers_comment_id' => $ai->id])->create();
        $mine->forceFill(['edited_at' => now()])->save();

        $this->webAs($this->admin, $this->alpha)->withoutVite()->get(route('tasks.index', ['task' => $task->id]))->assertInertia(function (AssertableInertia $page) use ($mine, $theirs, $ai, $answer): void {
            /** @var list<array{id: string, can_edit: bool, edited_at: string|null}> $list */
            $list = data_get($page->toArray(), 'props.selected_thread.comments');
            $byId = [];
            foreach ($list as $row) {
                $byId[$row['id']] = $row;
            }

            $this->assertTrue($byId[$mine->id]['can_edit']);
            $this->assertNotNull($byId[$mine->id]['edited_at']);
            $this->assertFalse($byId[$theirs->id]['can_edit']);
            $this->assertNull($byId[$theirs->id]['edited_at']);
            $this->assertFalse($byId[$ai->id]['can_edit']);
            $this->assertFalse($byId[$answer->id]['can_edit']);
        });
    }
}
