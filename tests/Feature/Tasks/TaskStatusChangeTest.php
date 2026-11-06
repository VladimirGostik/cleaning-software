<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\TaskStatusEnum;
use App\Models\Activity;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskStatusChangeTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->admin = $this->member($this->alpha, 'Admin');
    }

    public function test_api_moves_a_task_between_all_statuses_and_sets_completed_at_only_for_done(): void
    {
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();

        foreach (['in_progress', 'waiting', 'nice_to_have'] as $status) {
            $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/status", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('status', $status)
                ->assertJsonPath('is_active', true)
                ->assertJsonPath('completed_at', null);
        }

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/status", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('is_active', false);
        $this->assertNotNull(Task::query()->findOrFail($task->id)->completed_at);

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/status", ['status' => 'waiting'])
            ->assertOk()
            ->assertJsonPath('completed_at', null);
        $this->assertNull(Task::query()->findOrFail($task->id)->completed_at);
    }

    public function test_web_status_change_redirects_back_with_a_flash(): void
    {
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();

        $this->webAs($this->admin, $this->alpha)->from('/tasks')->post("/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertRedirect('/tasks')
            ->assertSessionHas('success', __('app.task_status_changed'));

        $this->assertSame(TaskStatusEnum::InProgress, Task::query()->findOrFail($task->id)->status);
    }

    public function test_the_same_status_writes_nothing_and_keeps_the_reminder_state(): void
    {
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create(['status' => TaskStatusEnum::Waiting, 'reminded_at' => now()->subHour()]);
        $before = Activity::query()->count();

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/status", ['status' => 'waiting'])
            ->assertOk()
            ->assertJsonPath('status', 'waiting');

        $this->assertSame($before, Activity::query()->count());
        $this->assertNotNull(Task::query()->findOrFail($task->id)->reminded_at);
    }

    public function test_moving_does_not_rearm_a_sent_reminder(): void
    {
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->done()->create(['reminded_at' => now()->subHour()]);

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/status", ['status' => 'open'])->assertOk();

        $this->assertNotNull(Task::query()->findOrFail($task->id)->reminded_at);
    }

    public function test_unknown_status_and_missing_status_are_rejected(): void
    {
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/status", ['status' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/status", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_user_without_edit_tasks_gets_403(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');
        $task = Task::factory()->assignedTo($this->membershipOf($cleaner, $this->alpha))->createdBy($this->admin)->create();

        $this->apiAs($cleaner, $this->alpha)->postJson("/api/tasks/{$task->id}/status", ['status' => 'done'])->assertForbidden();
        $this->webAs($cleaner, $this->alpha)->post("/tasks/{$task->id}/status", ['status' => 'done'])->assertForbidden();
    }

    public function test_personal_task_can_only_be_moved_by_its_creator(): void
    {
        $other = $this->member($this->alpha, 'Admin');
        $task = Task::factory()->global()->createdBy($this->admin)->create();

        $this->apiAs($other, $this->alpha)->postJson("/api/tasks/{$task->id}/status", ['status' => 'done'])->assertStatus(404);
        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/status", ['status' => 'waiting'])->assertOk();
    }

    public function test_complete_and_reopen_keep_their_rules_for_the_new_statuses(): void
    {
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create(['status' => TaskStatusEnum::NiceToHave]);

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/reopen")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/complete")->assertOk()->assertJsonPath('status', 'done');
        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/complete")->assertUnprocessable();
        $this->apiAs($this->admin, $this->alpha)->postJson("/api/tasks/{$task->id}/reopen")->assertOk()->assertJsonPath('status', 'open');
    }

    public function test_create_into_a_column_uses_the_requested_status_and_refuses_done(): void
    {
        $this->apiAs($this->admin, $this->alpha)->postJson('/api/tasks', ['title' => 'Column task', 'priority' => 'normal', 'status' => 'in_progress'])
            ->assertCreated()
            ->assertJsonPath('status', 'in_progress');
        $this->apiAs($this->admin, $this->alpha)->postJson('/api/tasks', ['title' => 'Default', 'priority' => 'normal'])
            ->assertCreated()
            ->assertJsonPath('status', 'open');
        $this->apiAs($this->admin, $this->alpha)->postJson('/api/tasks', ['title' => 'Done', 'priority' => 'normal', 'status' => 'done'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_ignores_status(): void
    {
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();

        $this->apiAs($this->admin, $this->alpha)->putJson("/api/tasks/{$task->id}", ['title' => 'Renamed', 'priority' => 'normal', 'status' => 'waiting'])->assertOk();

        $this->assertSame(TaskStatusEnum::Open, Task::query()->findOrFail($task->id)->status);
    }
}
