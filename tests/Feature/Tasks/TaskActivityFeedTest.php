<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Data\Tasks\TaskActivityEntryData;
use App\Data\Tasks\TaskActivityFeedData;
use App\Enums\TaskActivityFieldEnum;
use App\Enums\TaskActivityKindEnum;
use App\Enums\TaskPriorityEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TaskActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskActivityFeedTest extends TestCase
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
        $this->travelTo('2027-03-01 09:00:00');
    }

    private function firmTask(): Task
    {
        return Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create(['title' => 'A', 'description' => 'Secret body']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function edit(Task $task, User $by, array $attributes): void
    {
        $this->consoleContext();
        $this->actingAs($by);
        $task->update($attributes);
    }

    private function feed(Task $task, ?User $viewer = null): TaskActivityFeedData
    {
        $this->consoleContext();

        return app(TaskActivityService::class)->for($task->refresh(), $viewer ?? $this->admin);
    }

    /** @return list<TaskActivityEntryData> */
    private function updates(TaskActivityFeedData $feed): array
    {
        return array_values(array_filter($feed->entries, fn (TaskActivityEntryData $e): bool => $e->kind === TaskActivityKindEnum::Updated));
    }

    public function test_three_title_patches_in_a_window_make_one_entry_but_keep_three_rows(): void
    {
        $task = $this->firmTask();
        Activity::query()->delete();
        $this->webAs($this->admin, $this->firm);

        foreach (['B', 'C', 'D'] as $title) {
            $this->withoutVite()->patch(route('tasks.patch', $task), ['title' => $title])->assertRedirect();
            $this->travel(2)->minutes();
        }

        $this->assertSame(3, Activity::query()->where('event', 'updated')->count());
        $entries = $this->updates($this->feed($task));
        $this->assertCount(1, $entries);
        $this->assertCount(1, $entries[0]->changes);
        $this->assertSame(TaskActivityFieldEnum::Title, $entries[0]->changes[0]->field);
        $this->assertSame('A', $entries[0]->changes[0]->from);
        $this->assertSame('D', $entries[0]->changes[0]->to);
    }

    public function test_a_gap_over_the_window_splits_entries(): void
    {
        $task = $this->firmTask();
        $this->edit($task, $this->admin, ['title' => 'B']);
        $this->travel(11)->minutes();
        $this->edit($task, $this->admin, ['title' => 'C']);

        $this->assertCount(2, $this->updates($this->feed($task)));
    }

    public function test_another_causer_in_between_splits_entries(): void
    {
        $task = $this->firmTask();
        $other = $this->member($this->firm, 'Admin');
        $this->edit($task, $this->admin, ['title' => 'B']);
        $this->travel(1)->minutes();
        $this->edit($task, $other, ['title' => 'C']);
        $this->travel(1)->minutes();
        $this->edit($task, $this->admin, ['title' => 'D']);

        $entries = $this->updates($this->feed($task));
        $this->assertCount(3, $entries);
        $this->assertSame($other->name, $entries[1]->actor_name);
    }

    public function test_changes_reverted_inside_a_group_leave_no_entry(): void
    {
        $task = $this->firmTask();
        $this->edit($task, $this->admin, ['title' => 'B']);
        $this->travel(1)->minutes();
        $this->edit($task, $this->admin, ['title' => 'A']);

        $this->assertSame([], $this->updates($this->feed($task)));
    }

    public function test_description_change_carries_no_body(): void
    {
        $task = $this->firmTask();
        $this->edit($task, $this->admin, ['description' => 'New body']);

        $change = $this->updates($this->feed($task))[0]->changes[0];

        $this->assertSame(TaskActivityFieldEnum::Description, $change->field);
        $this->assertNull($change->from);
        $this->assertNull($change->to);
        $this->assertStringNotContainsString('body', json_encode($this->feed($task)->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_assignee_names_unassign_and_deleted_membership(): void
    {
        $task = $this->firmTask();
        $member = $this->member($this->firm, 'Admin');
        $membership = $this->membershipOf($member, $this->firm);

        $this->edit($task, $this->admin, ['assigned_membership_id' => $membership->id]);
        $this->assertSame($membership->display_name, $this->updates($this->feed($task))[0]->changes[0]->to);

        $this->travel(20)->minutes();
        $this->edit($task, $this->admin, ['assigned_membership_id' => null]);
        $entries = $this->updates($this->feed($task));
        $this->assertSame(TaskActivityFieldEnum::Assignee, $entries[1]->changes[0]->field);
        $this->assertNull($entries[1]->changes[0]->to);

        DB::table('tenant_memberships')->where('id', $membership->id)->delete();
        $entries = $this->updates($this->feed($task));
        $this->assertSame(__('app.task_activity_former_member'), $entries[0]->changes[0]->to);
        $this->assertSame(__('app.task_activity_former_member'), $entries[1]->changes[0]->from);
    }

    public function test_status_priority_and_due_values(): void
    {
        $task = $this->firmTask();
        $this->edit($task, $this->admin, [
            'status' => TaskStatusEnum::InProgress,
            'priority' => TaskPriorityEnum::High,
            'due_at' => '2027-03-10 08:00:00',
        ]);

        $changes = [];
        foreach ($this->updates($this->feed($task))[0]->changes as $change) {
            $changes[$change->field->value] = [$change->from, $change->to];
        }

        $this->assertSame(['open', 'in_progress'], $changes['status'] ?? null);
        $this->assertSame([null, 'high'][1], ($changes['priority'] ?? [])[1] ?? null);
        $this->assertSame([null, '2027-03-10T08:00:00+00:00'], $changes['due_at'] ?? null);
    }

    public function test_subject_columns_collapse_into_one_change(): void
    {
        $task = $this->firmTask();
        $client = Client::factory()->create(['tenant_id' => $this->firm->id]);
        $this->edit($task, $this->admin, ['taskable_type' => Client::class, 'taskable_id' => $client->id]);

        $changes = $this->updates($this->feed($task))[0]->changes;

        $this->assertCount(1, $changes);
        $this->assertSame(TaskActivityFieldEnum::Subject, $changes[0]->field);
        $this->assertNull($changes[0]->to);
    }

    public function test_attachment_rows_carry_the_name_on_firm_tasks_only_and_break_a_group(): void
    {
        $task = $this->firmTask();
        $global = Task::factory()->global()->createdBy($this->admin)->create();
        $this->edit($task, $this->admin, ['title' => 'B']);
        foreach ([$task, $global] as $t) {
            activity()->performedOn($t)->causedBy($this->admin)->event('attachment_added')
                ->withProperties(['attachment_id' => 'x'] + ($t->isGlobal() ? [] : ['name' => 'plan.pdf']))->log('attachment_added');
        }
        $this->edit($task, $this->admin, ['title' => 'C']);

        $entries = $this->feed($task)->entries;
        $kinds = array_map(fn (TaskActivityEntryData $e) => $e->kind, $entries);
        $this->assertSame([TaskActivityKindEnum::Created, TaskActivityKindEnum::Updated, TaskActivityKindEnum::AttachmentAdded, TaskActivityKindEnum::Updated], $kinds);
        $this->assertSame('plan.pdf', $entries[2]->attachment_name);
        $this->assertNull($this->feed($global)->entries[1]->attachment_name);
    }

    public function test_personal_task_shows_status_changes_but_not_title(): void
    {
        $task = Task::factory()->global()->createdBy($this->admin)->create(['title' => 'Mine']);
        $this->edit($task, $this->admin, ['title' => 'Renamed']);
        $this->edit($task, $this->admin, ['priority' => TaskPriorityEnum::High]);

        $changes = $this->updates($this->feed($task))[0]->changes;

        $this->assertCount(1, $changes);
        $this->assertSame(TaskActivityFieldEnum::Priority, $changes[0]->field);
    }

    public function test_created_entry_comes_first_with_the_creator(): void
    {
        $task = $this->firmTask();
        $this->travel(1)->hours();
        $this->edit($task, $this->admin, ['title' => 'B']);

        $entries = $this->feed($task)->entries;

        $this->assertSame('created', $entries[0]->id);
        $this->assertSame(TaskActivityKindEnum::Created, $entries[0]->kind);
        $this->assertSame($this->admin->name, $entries[0]->actor_name);
        $this->assertFalse($this->feed($task)->has_older);
    }

    public function test_over_the_limit_marks_older_and_drops_created(): void
    {
        config(['tasks.activity.limit' => 3]);
        $task = $this->firmTask();

        foreach (['B', 'C', 'D', 'E'] as $title) {
            $this->travel(30)->minutes();
            $this->edit($task, $this->admin, ['title' => $title]);
        }

        $feed = $this->feed($task);

        $this->assertTrue($feed->has_older);
        $this->assertCount(3, $feed->entries);
        $this->assertNotContains(TaskActivityKindEnum::Created, array_map(fn ($e) => $e->kind, $feed->entries));
        $this->assertSame('B', $feed->entries[0]->changes[0]->from);
        $this->assertSame('E', $feed->entries[2]->changes[0]->to);
    }

    /** @return array<string, mixed> */
    private function activityProps(?string $taskId): array
    {
        $query = $taskId === null ? [] : ['task' => $taskId];
        $full = $this->withoutVite()->get(route('tasks.index', $query))->assertOk();

        /** @var array<string, mixed> $props */
        $props = $this->withoutVite()->get(route('tasks.index', $query), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => data_get($full->viewData('page'), 'version'),
            'X-Inertia-Partial-Component' => 'Tasks/Index',
            'X-Inertia-Partial-Data' => 'selected_activity',
        ])->assertOk()->json('props');

        return $props;
    }

    public function test_panel_prop_is_present_for_a_visible_task_and_null_otherwise(): void
    {
        $task = $this->firmTask();
        $hidden = Task::factory()->forTenant($this->firm('Beta'))->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->firm);

        $visible = $this->activityProps($task->id);
        $this->assertCount(1, (array) data_get($visible, 'selected_activity.entries'));
        $this->assertFalse(data_get($visible, 'selected_activity.has_older'));

        $this->assertNull($this->activityProps($hidden->id)['selected_activity']);
        $this->assertNull($this->activityProps(null)['selected_activity']);
    }

    public function test_a_normal_visit_does_not_evaluate_the_feed(): void
    {
        $task = $this->firmTask();
        $this->webAs($this->admin, $this->firm);

        $this->withoutVite()->get(route('tasks.index', ['task' => $task->id]))->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('selected_activity')
            ->has('selected_thread'));
    }
}
