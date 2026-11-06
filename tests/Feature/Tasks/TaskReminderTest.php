<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Events\TaskAssigned;
use App\Events\TaskDue;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskDueNotification;
use App\Services\NotificationService;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskReminderTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->creator = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Admin', $this->creator);
        $this->consoleContext();
    }

    public function test_command_sends_one_reminder_even_when_run_twice(): void
    {
        NotificationFacade::fake();
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->dueReminder()->create();

        $this->artisanCommand('app:remind-tasks')->assertSuccessful();
        $this->artisanCommand('app:remind-tasks')->assertSuccessful();

        NotificationFacade::assertSentToTimes($this->creator, TaskDueNotification::class, 1);
        $this->assertNotNull(Task::unscopedForSystem()->findOrFail($task->id)->reminded_at);
    }

    public function test_command_skips_future_closed_and_already_reminded_tasks(): void
    {
        NotificationFacade::fake();
        Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->create(['remind_at' => now()->addHour()]);
        Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->done()->dueReminder()->create();
        Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->create(['remind_at' => now()->subHour(), 'reminded_at' => now()->subMinutes(10)]);
        Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->dueReminder()->create()->delete();

        $this->artisanCommand('app:remind-tasks')->assertSuccessful();

        NotificationFacade::assertNothingSent();
    }

    public function test_command_reminds_every_unfinished_status_but_not_done(): void
    {
        NotificationFacade::fake();
        $statuses = ['open', 'in_progress', 'waiting', 'nice_to_have'];

        foreach ($statuses as $status) {
            Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->dueReminder()->create(['status' => $status]);
        }

        Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->done()->dueReminder()->create();

        $this->artisanCommand('app:remind-tasks')->assertSuccessful();

        NotificationFacade::assertSentToTimes($this->creator, TaskDueNotification::class, count($statuses));
    }

    public function test_reminder_goes_to_the_assignee_when_assigned_else_the_creator(): void
    {
        NotificationFacade::fake();
        $assignee = $this->member($this->alpha, 'Sekretárka');
        Task::factory()->assignedTo($this->membershipOf($assignee, $this->alpha))->createdBy($this->creator)->dueReminder()->create();

        $this->artisanCommand('app:remind-tasks')->assertSuccessful();

        NotificationFacade::assertSentToTimes($assignee, TaskDueNotification::class, 1);
        NotificationFacade::assertNotSentTo($this->creator, TaskDueNotification::class);
    }

    public function test_global_reminder_row_has_null_tenant_and_shows_in_the_bell_of_every_firm(): void
    {
        $task = Task::factory()->global()->createdBy($this->creator)->dueReminder()->create(['title' => 'Renew licence']);

        $this->artisanCommand('app:remind-tasks')->assertSuccessful();

        $row = Notification::query()->sole();
        $this->assertNull($row->tenant_id);
        $this->assertSame('task.due', $row->type);
        $this->assertSame($this->creator->id, $row->notifiable_id);
        $this->assertSame(['task_id' => $task->id], $row->data['meta']);
        $this->assertStringContainsString('Renew licence', json_encode($row->data, JSON_THROW_ON_ERROR));

        foreach ([$this->alpha, $this->beta] as $tenant) {
            $this->assertSame(1, app(NotificationService::class)->bell($this->creator, $tenant->id)->unread_count);
        }
    }

    public function test_global_task_due_event_pushes_the_notification_for_the_creator_under_a_fake_queue(): void
    {
        Queue::fake();
        $task = Task::factory()->global()->createdBy($this->creator)->create();

        TaskDue::dispatch($task->id);

        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof TaskDueNotification
            && $job->notification->tenantId === null
            && $job->notification->taskId === $task->id
            && $job->notifiables->contains(fn (mixed $notifiable): bool => $notifiable instanceof User && $notifiable->id === $this->creator->id));
    }

    public function test_global_task_due_event_writes_a_null_tenant_row_on_the_sync_connection(): void
    {
        config(['queue.default' => 'sync']);
        $task = Task::factory()->global()->createdBy($this->creator)->create();

        TaskDue::dispatch($task->id);

        $row = Notification::query()->sole();
        $this->assertNull($row->tenant_id);
        $this->assertSame($this->creator->id, $row->notifiable_id);
    }

    public function test_task_assigned_event_from_a_console_context_notifies_the_assignee_with_the_firm_tenant(): void
    {
        $assignee = $this->member($this->alpha, 'Sekretárka');
        $task = Task::factory()->assignedTo($this->membershipOf($assignee, $this->alpha))->createdBy($this->creator)->create();

        TaskAssigned::dispatch($task->id);

        $row = Notification::query()->sole();
        $this->assertSame($this->alpha->id, $row->tenant_id);
        $this->assertSame('task.assigned', $row->type);
        $this->assertSame($assignee->id, $row->notifiable_id);
    }

    public function test_task_assigned_event_under_a_fake_queue_pushes_the_notification(): void
    {
        Queue::fake();
        $assignee = $this->member($this->alpha, 'Sekretárka');
        $task = Task::factory()->assignedTo($this->membershipOf($assignee, $this->alpha))->createdBy($this->creator)->create();

        TaskAssigned::dispatch($task->id);

        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof TaskAssignedNotification);
    }

    public function test_events_for_deleted_or_closed_tasks_are_ignored(): void
    {
        NotificationFacade::fake();
        $deleted = Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->create();
        $deleted->delete();
        $done = Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->done()->create();

        TaskDue::dispatch($deleted->id);
        TaskDue::dispatch($done->id);
        TaskAssigned::dispatch($deleted->id);

        NotificationFacade::assertNothingSent();
    }

    public function test_inactive_recipient_gets_nothing(): void
    {
        NotificationFacade::fake();
        $this->creator->forceFill(['is_active' => false])->save();
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->create();

        TaskDue::dispatch($task->id);

        NotificationFacade::assertNothingSent();
    }

    public function test_find_for_system_loads_global_tasks_without_context(): void
    {
        $task = Task::factory()->global()->createdBy($this->creator)->create();

        $this->assertSame($task->id, app(TaskService::class)->findForSystem($task->id)->id);
    }

    public function test_assign_request_dispatches_task_assigned(): void
    {
        Event::fake([TaskAssigned::class]);
        $assignee = $this->member($this->alpha, 'Sekretárka');
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->create();
        $this->webAs($this->creator, $this->alpha);

        $this->post(route('tasks.assign', $task), ['assigned_membership_id' => $this->membershipOf($assignee, $this->alpha)->id]);

        Event::assertDispatched(TaskAssigned::class, fn (TaskAssigned $event): bool => $event->taskId === $task->id);
    }

    public function test_reminder_claim_is_atomic_and_dispatches_once(): void
    {
        Event::fake([TaskDue::class]);
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->dueReminder()->create();
        $stale = Task::unscopedForSystem()->findOrFail($task->id);

        app(TaskService::class)->sendReminder($task);
        app(TaskService::class)->sendReminder($stale);

        Event::assertDispatchedTimes(TaskDue::class, 1);
    }

    public function test_delivery_after_the_task_was_deleted_does_not_fail(): void
    {
        Queue::fake();
        $task = Task::factory()->global()->createdBy($this->creator)->create(['title' => 'Gone soon']);
        TaskDue::dispatch($task->id);
        $task->delete();

        $job = null;
        Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $pushed) use (&$job): bool {
            if ($pushed->channels === [TenantDatabaseChannel::class]) {
                $job = $pushed;
            }

            return true;
        });
        $this->assertInstanceOf(SendQueuedNotifications::class, $job);
        $job->handle(app(ChannelManager::class));

        $row = Notification::query()->sole();
        $this->assertNull($row->tenant_id);
        $this->assertStringContainsString('Gone soon', json_encode($row->data, JSON_THROW_ON_ERROR));
    }

    public function test_removed_members_get_no_notifications(): void
    {
        NotificationFacade::fake();
        $assignee = $this->member($this->alpha, 'Sekretárka');
        $membership = $this->membershipOf($assignee, $this->alpha);
        $assigned = Task::factory()->assignedTo($membership)->createdBy($this->creator)->dueReminder()->create();
        $membership->update(['is_active' => false]);

        TaskDue::dispatch($assigned->id);
        TaskAssigned::dispatch($assigned->id);

        NotificationFacade::assertNothingSent();

        $this->creator->memberships()->where('tenant_id', $this->alpha->id)->update(['is_active' => false]);
        $own = Task::factory()->forTenant($this->alpha)->createdBy($this->creator)->create();
        TaskDue::dispatch($own->id);

        NotificationFacade::assertNothingSent();
    }
}
