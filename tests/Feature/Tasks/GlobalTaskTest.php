<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Activity;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class GlobalTaskTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $owner;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->owner = $this->member($this->alpha, 'Admin');
        $this->other = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Admin', $this->other);
    }

    public function test_another_users_global_task_is_not_found_by_id_on_web_and_api(): void
    {
        $task = Task::factory()->global()->createdBy($this->owner)->create();

        $this->webAs($this->other, $this->alpha);
        $this->get(route('tasks.show', $task))->assertNotFound();
        $this->put(route('tasks.update', $task), ['title' => 'x', 'priority' => 'low'])->assertNotFound();
        $this->delete(route('tasks.destroy', $task))->assertNotFound();

        $this->apiAs($this->other, $this->alpha)->getJson('/api/tasks/'.$task->id)->assertNotFound();
        $this->apiAs($this->other, $this->beta)->getJson('/api/tasks/'.$task->id)->assertNotFound();
    }

    public function test_another_users_global_task_is_absent_from_lists_in_every_tenant(): void
    {
        $task = Task::factory()->global()->createdBy($this->owner)->create();
        $mine = Task::factory()->global()->createdBy($this->other)->create();

        foreach ([$this->alpha, $this->beta] as $tenant) {
            $this->webAs($this->other, $tenant);
            $this->withoutVite()->get('/tasks')->assertInertia(function (AssertableInertia $page) use ($task, $mine): void {
                /** @var array{tasks: array{data: list<array{id: string}>}} $props */
                $props = $page->toArray()['props'];
                $ids = array_column($props['tasks']['data'], 'id');
                $this->assertNotContains($task->id, $ids);
                $this->assertContains($mine->id, $ids);
            });

            foreach (['company', 'all'] as $scope) {
                /** @var list<string> $ids */
                $ids = $this->apiAs($this->other, $tenant)->getJson('/api/tasks?scope='.$scope)->assertOk()->json('data.*.id');
                $this->assertNotContains($task->id, $ids);
                $this->assertContains($mine->id, $ids);
            }
        }
    }

    public function test_the_creator_reaches_her_global_task_under_any_firm(): void
    {
        $task = Task::factory()->global()->createdBy($this->other)->create();

        $this->apiAs($this->other, $this->beta)->getJson('/api/tasks/'.$task->id)
            ->assertOk()
            ->assertJsonPath('is_global', true)
            ->assertJsonPath('tenant_id', null)
            ->assertJsonPath('can.assign', false);
    }

    public function test_console_context_sees_no_global_rows(): void
    {
        Task::factory()->global()->createdBy($this->owner)->create();
        Task::factory()->forTenant($this->alpha)->createdBy($this->owner)->create();

        $this->consoleContext();

        $this->assertSame(0, Task::query()->count());
    }

    public function test_user_without_task_permissions_cannot_create_a_global_task(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');
        $lead = $this->member($this->alpha, 'Vedúca');

        $this->apiAs($cleaner, $this->alpha)
            ->postJson('/api/tasks', ['title' => 'G', 'priority' => 'low', 'tenant_id' => null])
            ->assertForbidden();

        $this->apiAs($lead, $this->alpha)
            ->postJson('/api/tasks', ['title' => 'G', 'priority' => 'low', 'tenant_id' => null])
            ->assertCreated()
            ->assertJsonPath('is_global', true);
    }

    public function test_global_task_creation_needs_the_permission_in_some_tenant_not_necessarily_the_active_one(): void
    {
        $user = $this->member($this->alpha, 'Interná upratovačka');
        $this->member($this->beta, 'Vedúca', $user);

        $this->apiAs($user, $this->alpha)
            ->postJson('/api/tasks', ['title' => 'G', 'priority' => 'low', 'tenant_id' => null])
            ->assertCreated();
    }

    public function test_global_task_cannot_be_assigned(): void
    {
        $task = Task::factory()->global()->createdBy($this->owner)->create();
        $membership = $this->membershipOf($this->owner, $this->alpha);

        $this->apiAs($this->owner, $this->alpha)
            ->postJson('/api/tasks/'.$task->id.'/assign', ['assigned_membership_id' => $membership->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_membership_id');

        $this->apiAs($this->owner, $this->alpha)
            ->postJson('/api/tasks', [
                'title' => 'G', 'priority' => 'low', 'tenant_id' => null, 'assigned_membership_id' => $membership->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_membership_id');
    }

    public function test_database_rejects_an_assigned_global_task(): void
    {
        $membership = $this->membershipOf($this->owner, $this->alpha);

        $this->expectException(QueryException::class);

        Task::factory()->global()->createdBy($this->owner)->create(['assigned_membership_id' => $membership->id]);
    }

    public function test_creator_deletes_her_global_task_directly(): void
    {
        $task = Task::factory()->global()->createdBy($this->owner)->create();

        $this->apiAs($this->owner, $this->alpha)->deleteJson('/api/tasks/'.$task->id)->assertNoContent();

        $this->assertSoftDeleted($task);
    }

    public function test_global_task_activity_keeps_a_null_tenant_while_a_tenant_is_bound(): void
    {
        $this->webAs($this->owner, $this->alpha);

        $this->post('/tasks', ['title' => 'Personal', 'priority' => 'low', 'tenant_id' => null])->assertRedirect();
        $this->post('/tasks', ['title' => 'Firm', 'priority' => 'low'])->assertRedirect();

        $global = Task::query()->where('title', 'Personal')->sole();
        $firm = Task::query()->where('title', 'Firm')->sole();

        $globalLog = Activity::query()->where('subject_id', $global->id)->sole();
        $this->assertNull($globalLog->tenant_id);
        $this->assertSame($this->alpha->id, Activity::query()->where('subject_id', $firm->id)->sole()->tenant_id);
    }

    public function test_null_tenant_task_activity_is_visible_to_a_member_of_the_actors_firms(): void
    {
        $this->webAs($this->owner, $this->alpha);
        $this->post('/tasks', ['title' => 'Personal', 'priority' => 'low', 'tenant_id' => null])->assertRedirect();
        $global = Task::query()->sole();

        $this->webAs($this->other, $this->alpha);

        $this->withoutVite()->get('/audit-logs')->assertInertia(function (AssertableInertia $page) use ($global): void {
            /** @var array{activities: array{data: list<array{subject_id: string|null, subject_type: string|null}>}} $props */
            $props = $page->toArray()['props'];
            $rows = array_filter($props['activities']['data'], fn (array $row): bool => $row['subject_id'] === $global->id);
            $this->assertCount(1, $rows);
            $this->assertSame('Task', array_values($rows)[0]['subject_type']);
        });
    }

    public function test_audit_log_detail_renders_when_the_subject_task_is_hidden_from_the_viewer(): void
    {
        $this->webAs($this->owner, $this->alpha);
        $this->post('/tasks', ['title' => 'Marker', 'priority' => 'low', 'tenant_id' => null])->assertRedirect();
        $task = Task::query()->sole();
        $activity = Activity::query()->where('subject_id', $task->id)->sole();

        $this->webAs($this->other, $this->alpha);

        $this->withoutVite()->get(route('audit-logs.show', $activity))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('activity.subject_id', $task->id)->etc());
    }
}
