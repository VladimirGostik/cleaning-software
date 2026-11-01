<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Contracts\SendsPush;
use App\Enums\TaskRunOutcomeEnum;
use App\Enums\TaskRunStatusEnum;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Services\Ai\TaskRunResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakePushSender;
use Tests\Support\CreatesTaskRunFixtures;
use Tests\TestCase;

final class TaskRunNotificationTest extends TestCase
{
    use CreatesTaskRunFixtures;
    use RefreshDatabase;

    private FakePushSender $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTaskRunFixtures(fakeQueue: false);
        $this->sender = new FakePushSender;
        $this->app->instance(SendsPush::class, $this->sender);
    }

    public function test_only_the_starter_hears_about_a_question_and_the_text_is_fixed(): void
    {
        $assignee = $this->member($this->alpha, 'Sekretárka');
        $creator = $this->member($this->alpha, 'Sekretárka');
        $task = $this->firmTask($creator);
        $task->update(['assigned_membership_id' => $this->membershipOf($assignee, $this->alpha)->id]);
        DeviceToken::factory()->for($this->admin)->create(['token' => 'tok-admin']);
        $this->runner->result = new TaskRunResult(TaskRunOutcomeEnum::Question, 'Secret question about the client');

        $this->execute($this->queuedRun($task, $this->admin));

        $row = Notification::query()->forRecipient($this->admin)->where('type', 'task.ai_run')->sole();
        $this->assertStringNotContainsString('Secret question', (string) json_encode($row->data));
        $this->assertSame('/tasks/'.$task->id, $this->sender->sent[0]['message']->route);
        $this->assertStringNotContainsString('Secret question', $this->sender->sent[0]['message']->body);
        $this->assertSame(0, Notification::query()->forRecipient($assignee)->count());
        $this->assertSame(0, Notification::query()->forRecipient($creator)->count());
    }

    public function test_an_automatic_close_notifies_only_the_starter(): void
    {
        $assignee = $this->member($this->alpha, 'Sekretárka');
        $task = $this->firmTask();
        $task->update(['assigned_membership_id' => $this->membershipOf($assignee, $this->alpha)->id]);

        $this->execute($this->queuedRun($task, $this->admin));

        $this->assertSame(1, Notification::query()->forRecipient($this->admin)->where('type', 'task.ai_run')->count());
        $this->assertSame(0, Notification::query()->forRecipient($assignee)->count());
    }

    public function test_a_comment_notification_pushes_the_plain_task_route_without_the_comment_text(): void
    {
        $creator = $this->memberWith($this->alpha, 'Sekretárka', 'access mobile app');
        $task = $this->firmTask($creator);
        DeviceToken::factory()->for($creator)->create(['token' => 'tok-creator']);

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Private wording'])->assertCreated();

        $row = Notification::query()->forRecipient($creator)->where('type', 'task.commented')->sole();
        $this->assertStringNotContainsString('Private wording', (string) json_encode($row->data));
        $this->assertSame('/tasks/'.$task->id, $this->sender->sent[0]['message']->route);
        $this->assertSame(['tok-creator'], $this->sender->allTokens());
    }

    public function test_an_answer_notifies_the_starter_of_the_question(): void
    {
        $task = $this->firmTask();
        $starter = $this->member($this->alpha, 'Vedúca');
        $run = $this->finishedRun($task, $starter, TaskRunStatusEnum::NeedsInput);
        $question = $this->aiComment($task, $run, 'Which client?');

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Alfa', 'answers_comment_id' => $question->id])->assertCreated();

        $this->assertSame(1, Notification::query()->forRecipient($starter)->where('type', 'task.commented')->count());
    }
}
