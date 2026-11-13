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
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskApiPatchTest extends TestCase
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
            'assigned_membership_id' => $task->assigned_membership_id,
            'tenant_id' => $task->tenant_id,
        ];
    }

    public function test_priority_only_patch_returns_detail_and_changes_only_priority(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);
        Activity::query()->delete();

        $this->apiAs($this->admin, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", ['priority' => 'high'])
            ->assertOk()
            ->assertJsonPath('id', $task->id)
            ->assertJsonPath('priority', 'high')
            ->assertJsonPath('title', 'Original')
            ->assertJsonPath('description', 'Keep me')
            ->assertJsonStructure(['id', 'tenant_id', 'title', 'description', 'status', 'priority', 'due_at', 'remind_at', 'remind_on', 'remind_slot']);

        $after = $this->snapshot($task);
        $this->assertSame(TaskPriorityEnum::High, $after['priority']);
        $before['priority'] = $after['priority'];
        $this->assertSame($before, $after);

        $changes = Activity::query()->where('subject_id', $task->id)->sole()->attributeChangesArray();
        $this->assertIsArray($changes);
        $this->assertIsArray($changes['attributes']);
        $this->assertSame(['priority'], array_keys($changes['attributes']));
    }

    public function test_title_and_description_patch_keeps_due_and_reminder(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);

        $this->apiAs($this->admin, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", ['title' => 'Renamed', 'description' => 'New text'])
            ->assertOk()
            ->assertJsonPath('title', 'Renamed')
            ->assertJsonPath('description', 'New text');

        $after = $this->snapshot($task);
        $this->assertSame('Renamed', $after['title']);
        $this->assertSame('New text', $after['description']);
        $this->assertSame($before['due_at'], $after['due_at']);
        $this->assertSame($before['remind_at'], $after['remind_at']);
        $this->assertSame($before['reminded_at'], $after['reminded_at']);
    }

    public function test_description_null_clears_it_and_others_stay(): void
    {
        $task = $this->richTask();

        $this->apiAs($this->admin, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", ['description' => null])
            ->assertOk()
            ->assertJsonPath('description', null);

        $task->refresh();
        $this->assertNull($task->description);
        $this->assertSame('Original', $task->title);
        $this->assertNotNull($task->due_at);
        $this->assertNotNull($task->remind_at);
    }

    public function test_empty_title_is_rejected_on_title_only_and_task_is_unchanged(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);

        $response = $this->apiAs($this->admin, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", ['title' => ''])
            ->assertUnprocessable();

        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertSame(['title'], array_keys($errors));
        $this->assertSame($before, $this->snapshot($task));
    }

    public function test_reminder_pair_sets_the_slot_instant_and_rearms(): void
    {
        $task = $this->richTask();

        $this->apiAs($this->admin, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", ['remind_on' => '2027-01-15', 'remind_slot' => 'noon'])
            ->assertOk()
            ->assertJsonPath('remind_on', '2027-01-15')
            ->assertJsonPath('remind_slot', 'noon');

        $task->refresh();
        $this->assertSame('2027-01-15 11:00:00', $task->remind_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($task->reminded_at);
        $this->assertSame('Keep me', $task->description);
    }

    public function test_both_reminder_keys_null_clear_the_reminder(): void
    {
        $task = $this->richTask();

        $this->apiAs($this->admin, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", ['remind_on' => null, 'remind_slot' => null])
            ->assertOk()
            ->assertJsonPath('remind_at', null);

        $task->refresh();
        $this->assertNull($task->remind_at);
        $this->assertNull($task->reminded_at);
        $this->assertSame('Keep me', $task->description);
    }

    public function test_reminder_date_without_slot_errors_on_slot(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);

        $this->apiAs($this->admin, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", ['remind_on' => '2027-01-15'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remind_slot');

        $this->assertSame($before, $this->snapshot($task));
    }

    public function test_reminder_slot_without_date_errors_on_date(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);

        $this->apiAs($this->admin, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", ['remind_slot' => 'noon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remind_on');

        $this->assertSame($before, $this->snapshot($task));
    }

    public function test_empty_body_changes_nothing_and_writes_no_activity(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);
        Activity::query()->delete();

        $this->apiAs($this->admin, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", [])
            ->assertOk()
            ->assertJsonPath('id', $task->id);

        $this->assertSame($before, $this->snapshot($task));
        $this->assertSame(0, Activity::query()->where('subject_id', $task->id)->count());
    }

    public function test_status_assignee_and_tenant_keys_are_ignored(): void
    {
        $task = $this->richTask();
        $before = $this->snapshot($task);
        $other = $this->firm('Beta');
        $membership = $this->membershipOf($this->admin, $this->firm);
        Activity::query()->delete();

        $this->apiAs($this->admin, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", [
                'status' => 'done',
                'assigned_membership_id' => $membership->id,
                'tenant_id' => $other->id,
            ])
            ->assertOk();

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

        $this->apiAs($viewer, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", ['priority' => 'high'])
            ->assertForbidden();

        $this->assertSame(TaskPriorityEnum::Normal, $task->refresh()->priority);
    }

    public function test_task_of_another_firm_is_not_found(): void
    {
        $task = Task::factory()->forTenant($this->firm('Beta'))->createdBy($this->admin)->create(['title' => 'Beta task']);

        $this->apiAs($this->admin, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", ['title' => 'Hijack'])
            ->assertNotFound();

        $this->assertSame('Beta task', Task::unscopedForSystem()->findOrFail($task->id)->title);
    }

    public function test_another_users_personal_task_is_not_found(): void
    {
        $task = Task::factory()->global()->createdBy($this->admin)->create(['title' => 'Mine']);
        $other = $this->member($this->firm, 'Admin');

        $this->apiAs($other, $this->firm)
            ->patchJson("/api/tasks/{$task->id}", ['title' => 'Hijack'])
            ->assertNotFound();

        $this->assertSame('Mine', Task::unscopedForSystem()->findOrFail($task->id)->title);
    }

    public function test_mcp_token_cannot_patch(): void
    {
        $task = $this->richTask();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->admin, ['mcp']);

        $this->withHeaders(['X-Tenant-Id' => $this->firm->id])
            ->patchJson("/api/tasks/{$task->id}", ['priority' => 'high'])
            ->assertForbidden();

        $this->assertSame(TaskPriorityEnum::Normal, $task->refresh()->priority);
    }

    public function test_put_without_priority_is_still_rejected(): void
    {
        $task = $this->richTask();

        $this->apiAs($this->admin, $this->firm)
            ->putJson("/api/tasks/{$task->id}", ['title' => 'Only title'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');

        $this->assertSame('Original', $task->refresh()->title);
    }

    public function test_full_put_without_description_clears_it(): void
    {
        $task = $this->richTask();

        $this->apiAs($this->admin, $this->firm)
            ->putJson("/api/tasks/{$task->id}", ['title' => 'Replaced', 'priority' => 'low'])
            ->assertOk()
            ->assertJsonPath('description', null);

        $task->refresh();
        $this->assertSame('Replaced', $task->title);
        $this->assertNull($task->description);
    }
}
