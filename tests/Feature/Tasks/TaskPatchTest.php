<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\PermissionEnum;
use App\Enums\TaskPriorityEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskPatchTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $firm;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->firm = $this->firm('Alpha');
        $this->admin = $this->member($this->firm, 'Admin');
    }

    private function richTask(): Task
    {
        $client = Client::factory()->create(['tenant_id' => $this->firm->id]);

        return Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create([
            'title' => 'Original',
            'description' => 'Keep me',
            'priority' => TaskPriorityEnum::Normal,
            'due_at' => '2027-03-10 08:00:00',
            'remind_at' => '2027-03-09 11:00:00',
            'reminded_at' => '2027-03-09 11:05:00',
            'taskable_type' => Client::class,
            'taskable_id' => $client->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(Task $task): array
    {
        $task->refresh();

        return [
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'due_at' => $task->due_at?->toIso8601String(),
            'remind_at' => $task->remind_at?->toIso8601String(),
            'reminded_at' => $task->reminded_at?->toIso8601String(),
            'taskable_type' => $task->taskable_type,
            'taskable_id' => $task->taskable_id,
        ];
    }

    public function test_title_and_priority_patch_keeps_every_other_field(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", ['title' => 'Renamed', 'priority' => 'low'])->assertRedirect();

        $task->refresh();
        $this->assertSame('Renamed', $task->title);
        $this->assertSame(TaskPriorityEnum::Low, $task->priority);
        $after = $this->snapshot($task);
        $this->assertSame(array_slice($before, 3), array_slice($after, 3));
        $this->assertSame('Keep me', $task->description);
        $this->assertSame('2027-03-09 11:05:00', $task->reminded_at?->utc()->format('Y-m-d H:i:s'));
    }

    public function test_priority_only_patch_changes_priority_without_flash_and_logs_only_it(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);
        Activity::query()->delete();
        $this->webAs($this->admin, $this->firm);

        $response = $this->patch("/tasks/{$task->id}", ['priority' => 'high']);

        $response->assertRedirect();
        $response->assertSessionMissing('success');
        $after = $this->snapshot($task);
        $this->assertSame(TaskPriorityEnum::High, $after['priority']);
        $before['priority'] = $after['priority'];
        $this->assertSame($before, $after);

        $activity = Activity::query()->where('subject_id', $task->id)->sole();
        $changes = $activity->attributeChangesArray();
        $this->assertIsArray($changes);
        $this->assertIsArray($changes['attributes']);
        $this->assertSame(['priority'], array_keys($changes['attributes']));
    }

    public function test_empty_title_is_rejected_on_title_only_and_task_is_unchanged(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);
        $this->webAs($this->admin, $this->firm);

        $response = $this->patch("/tasks/{$task->id}", ['title' => '']);

        $response->assertSessionHasErrors('title');
        $errors = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $errors);
        $this->assertSame(['title'], array_keys($errors->getBag('default')->toArray()));
        $this->assertSame($before, $this->snapshot($task));
    }

    public function test_title_over_200_chars_is_rejected(): void
    {
        $task = $this->richTask();
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", ['title' => str_repeat('a', 201)])->assertSessionHasErrors('title');
        $this->assertSame('Original', $task->refresh()->title);
    }

    public function test_description_null_clears_and_other_fields_stay(): void
    {
        $task = $this->richTask();
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", ['description' => null])->assertRedirect();

        $task->refresh();
        $this->assertNull($task->description);
        $this->assertSame('Original', $task->title);
        $this->assertNotNull($task->due_at);
        $this->assertNotNull($task->remind_at);
    }

    public function test_reminder_pair_sets_the_slot_instant_and_rearms(): void
    {
        $task = $this->richTask();
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", ['remind_on' => '2027-01-15', 'remind_slot' => 'noon'])->assertRedirect();

        $task->refresh();
        $this->assertSame('2027-01-15 11:00:00', $task->remind_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($task->reminded_at);
        $this->assertSame('Keep me', $task->description);
    }

    public function test_both_reminder_keys_null_clear_remind_at_and_reminded_at(): void
    {
        $task = $this->richTask();
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", ['remind_on' => null, 'remind_slot' => null])->assertRedirect();

        $task->refresh();
        $this->assertNull($task->remind_at);
        $this->assertNull($task->reminded_at);
        $this->assertSame('Keep me', $task->description);
    }

    public function test_reminder_date_without_slot_errors_on_slot(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", ['remind_on' => '2027-01-15'])->assertSessionHasErrors('remind_slot');
        $this->assertSame($before, $this->snapshot($task));
    }

    public function test_reminder_slot_without_date_errors_on_date(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", ['remind_slot' => 'noon'])->assertSessionHasErrors('remind_on');
        $this->assertSame($before, $this->snapshot($task));
    }

    public function test_null_date_with_a_slot_errors_on_date(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", ['remind_on' => null, 'remind_slot' => 'noon'])->assertSessionHasErrors('remind_on');
        $this->assertSame($before, $this->snapshot($task));
    }

    public function test_title_only_patch_keeps_a_legacy_off_slot_reminder(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create([
            'remind_at' => '2027-01-15 09:00:30',
            'reminded_at' => '2027-01-15 09:05:00',
        ]);
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", ['title' => 'Only title'])->assertRedirect();

        $task->refresh();
        $this->assertSame('Only title', $task->title);
        $this->assertSame('2027-01-15 09:00:30', $task->remind_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-15 09:05:00', $task->reminded_at?->utc()->format('Y-m-d H:i:s'));
    }

    public function test_empty_payload_changes_nothing_and_writes_no_activity(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);
        Activity::query()->delete();
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", [])->assertRedirect();

        $this->assertSame($before, $this->snapshot($task));
        $this->assertSame(0, Activity::query()->where('subject_id', $task->id)->count());
    }

    public function test_status_key_is_ignored_and_writes_no_activity(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);
        Activity::query()->delete();
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", ['status' => 'done'])->assertRedirect();

        $this->assertSame($before, $this->snapshot($task));
        $this->assertSame(TaskStatusEnum::Open, $task->refresh()->status);
        $this->assertSame(0, Activity::query()->where('subject_id', $task->id)->count());
    }

    public function test_actor_without_edit_tasks_gets_403(): void
    {
        $task = $this->richTask();
        $viewer = User::factory()->create();
        $this->member($this->firm, 'Admin', $viewer);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->firm->id);
        $viewer->unsetRelation('roles')->unsetRelation('permissions');
        $viewer->syncRoles([]);
        $viewer->syncPermissions([PermissionEnum::ViewTasks->value]);
        $this->webAs($viewer, $this->firm);

        $this->patch("/tasks/{$task->id}", ['priority' => 'high'])->assertForbidden();
        $this->assertSame(TaskPriorityEnum::Normal, $task->refresh()->priority);
    }

    public function test_another_users_personal_task_is_not_found(): void
    {
        $task = Task::factory()->global()->createdBy($this->admin)->create(['title' => 'Mine']);
        $other = $this->member($this->firm, 'Admin');
        $this->webAs($other, $this->firm);

        $this->patch("/tasks/{$task->id}", ['title' => 'Hijack'])->assertNotFound();
        $this->assertSame('Mine', Task::unscopedForSystem()->findOrFail($task->id)->title);
    }

    public function test_task_of_another_firm_is_not_found(): void
    {
        $task = Task::factory()->forTenant($this->firm('Beta'))->createdBy($this->admin)->create(['title' => 'Beta task']);
        $this->webAs($this->admin, $this->firm);

        $this->patch("/tasks/{$task->id}", ['title' => 'Hijack'])->assertNotFound();
        $this->assertSame('Beta task', Task::unscopedForSystem()->findOrFail($task->id)->title);
    }
}
