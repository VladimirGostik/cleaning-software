<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskQuickCreateTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $firm;

    private User $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->firm = $this->firm('Alpha');
        $this->lead = $this->member($this->firm, 'Vedúca');
    }

    public function test_creator_without_delete_permission_discards_own_task_over_api(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->lead)->create();

        $this->apiAs($this->lead, $this->firm)->deleteJson('/api/tasks/'.$task->id)->assertNoContent();

        $this->assertSoftDeleted($task);
    }

    public function test_creator_without_delete_permission_discards_own_task_on_web(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->lead)->create();
        $this->webAs($this->lead, $this->firm);

        $this->delete(route('tasks.destroy', $task))->assertRedirect();

        $this->assertSoftDeleted($task);
    }

    public function test_creator_can_still_delete_after_thirty_days(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->lead)->create();

        $this->travel(30)->days();

        $this->apiAs($this->lead, $this->firm)->deleteJson('/api/tasks/'.$task->id)->assertNoContent();
    }

    public function test_other_users_task_stays_protected(): void
    {
        $other = $this->member($this->firm, 'Vedúca');
        $task = Task::factory()->forTenant($this->firm)->createdBy($other)->create();

        $this->apiAs($this->lead, $this->firm)->deleteJson('/api/tasks/'.$task->id)->assertForbidden();

        $this->assertNotSoftDeleted($task);
    }

    public function test_admin_deletes_any_visible_firm_task(): void
    {
        $admin = $this->member($this->firm, 'Admin');
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->lead)->create();

        $this->apiAs($admin, $this->firm)->deleteJson('/api/tasks/'.$task->id)->assertNoContent();
    }

    public function test_personal_task_is_creator_only(): void
    {
        $admin = $this->member($this->firm, 'Admin');
        $task = Task::factory()->global()->createdBy($this->lead)->create();

        $this->apiAs($admin, $this->firm)->deleteJson('/api/tasks/'.$task->id)->assertNotFound();
        $this->assertNotSoftDeleted($task);

        $this->apiAs($this->lead, $this->firm)->deleteJson('/api/tasks/'.$task->id)->assertNoContent();
    }

    public function test_creator_who_lost_create_permission_cannot_delete(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->lead)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->firm->id);
        $this->lead->syncRoles([Role::inTenant($this->firm->id)->where('name', 'Interná upratovačka')->firstOrFail()]);

        $this->apiAs($this->lead, $this->firm)->deleteJson('/api/tasks/'.$task->id)->assertForbidden();

        $this->assertNotSoftDeleted($task);
    }

    public function test_can_delete_flag_follows_the_rule(): void
    {
        $own = Task::factory()->forTenant($this->firm)->createdBy($this->lead)->create();
        $other = Task::factory()->forTenant($this->firm)->createdBy($this->member($this->firm, 'Vedúca'))->create();

        $this->apiAs($this->lead, $this->firm);

        $this->getJson('/api/tasks/'.$own->id)->assertOk()->assertJsonPath('can.delete', true);
        $this->getJson('/api/tasks/'.$other->id)->assertOk()->assertJsonPath('can.delete', false);
    }

    public function test_api_create_with_title_and_priority_only(): void
    {
        $this->apiAs($this->lead, $this->firm)
            ->postJson('/api/tasks', ['title' => 'Quick', 'priority' => 'normal'])
            ->assertCreated()
            ->assertJsonPath('title', 'Quick');
    }

    public function test_api_create_requires_priority(): void
    {
        $this->apiAs($this->lead, $this->firm)
            ->postJson('/api/tasks', ['title' => 'Quick'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_api_create_accepts_waiting_but_not_done(): void
    {
        $this->apiAs($this->lead, $this->firm);

        $this->postJson('/api/tasks', ['title' => 'W', 'priority' => 'low', 'status' => 'waiting'])->assertCreated()->assertJsonPath('status', 'waiting');
        $this->postJson('/api/tasks', ['title' => 'D', 'priority' => 'low', 'status' => 'done'])->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_api_create_with_null_tenant_is_personal(): void
    {
        $this->apiAs($this->lead, $this->firm)
            ->postJson('/api/tasks', ['title' => 'Mine', 'priority' => 'low', 'tenant_id' => null])
            ->assertCreated();

        $this->assertNull(Task::query()->sole()->tenant_id);
    }

    public function test_store_keeps_the_callers_tasks_query_and_skips_the_flash(): void
    {
        $this->webAs($this->lead, $this->firm);

        $response = $this->withHeader('Referer', url('/tasks?view=board&filter[priority]=high'))
            ->post('/tasks', ['title' => 'Q', 'priority' => 'low']);

        $task = Task::query()->sole();
        $response->assertRedirect(route('tasks.index', ['view' => 'board', 'filter' => ['priority' => 'high'], 'task' => $task->id]));
        $response->assertSessionMissing('success');
    }

    public function test_store_from_another_page_redirects_to_plain_index(): void
    {
        $this->webAs($this->lead, $this->firm);

        $this->withHeader('Referer', url('/clients/abc?x=1'))
            ->post('/tasks', ['title' => 'Q', 'priority' => 'low'])
            ->assertRedirect(route('tasks.index', ['task' => Task::query()->sole()->id]));
    }

    public function test_store_ignores_foreign_host_and_crafted_path(): void
    {
        $this->webAs($this->lead, $this->firm);

        foreach (['https://evil.example/tasks?a=1', url('/tasks/../clients?a=1'), url('/tasksx?a=1')] as $referer) {
            Task::query()->delete();
            $this->withHeader('Referer', $referer)->post('/tasks', ['title' => 'Q', 'priority' => 'low'])
                ->assertRedirect(route('tasks.index', ['task' => Task::query()->sole()->id]));
        }
    }

    public function test_store_in_another_company_keeps_query_without_task_and_flashes(): void
    {
        $beta = $this->firm('Beta');
        $this->member($beta, 'Admin', $this->lead);
        $this->webAs($this->lead, $this->firm);

        $this->withHeader('Referer', url('/tasks?filter[priority]=high'))
            ->post('/tasks', ['title' => 'Q', 'priority' => 'low', 'tenant_id' => $beta->id])
            ->assertRedirect(route('tasks.index', ['filter' => ['priority' => 'high']]))
            ->assertSessionHas('success');
    }

    public function test_destroy_drops_task_key_keeps_filters_and_flashes(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->lead)->create();
        $this->webAs($this->lead, $this->firm);

        $this->withHeader('Referer', url('/tasks?filter[priority]=high&task='.$task->id))
            ->delete(route('tasks.destroy', $task))
            ->assertRedirect(route('tasks.index', ['filter' => ['priority' => 'high']]))
            ->assertSessionHas('success', __('app.task_deleted'));
    }

    public function test_store_and_destroy_without_a_referer_redirect_to_the_plain_index(): void
    {
        $this->webAs($this->lead, $this->firm);

        $this->post('/tasks', ['title' => 'Q', 'priority' => 'low'])
            ->assertRedirect(route('tasks.index', ['task' => Task::query()->sole()->id]));

        $this->delete(route('tasks.destroy', Task::query()->sole()))
            ->assertRedirect(route('tasks.index'))
            ->assertSessionHas('success');
    }

    public function test_without_a_referer_the_previous_session_url_is_used(): void
    {
        $this->webAs($this->lead, $this->firm);
        $this->withoutVite()->get('/tasks?filter[priority]=high');

        $this->post('/tasks', ['title' => 'Q', 'priority' => 'low'])
            ->assertRedirect(route('tasks.index', ['filter' => ['priority' => 'high'], 'task' => Task::query()->sole()->id]));
    }

    public function test_store_strips_an_array_task_key_and_keeps_filters(): void
    {
        $this->webAs($this->lead, $this->firm);

        $response = $this->withHeader('Referer', url('/tasks?task[]=x&filter[priority]=high'))
            ->post('/tasks', ['title' => 'Q', 'priority' => 'low']);

        $response->assertRedirect(route('tasks.index', ['filter' => ['priority' => 'high'], 'task' => Task::query()->sole()->id]));
    }

    public function test_creator_who_lost_view_tasks_cannot_delete(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->lead)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->firm->id);
        $this->lead->syncRoles([Role::inTenant($this->firm->id)->where('name', 'Interná upratovačka')->firstOrFail()]);

        $this->webAs($this->lead, $this->firm)->delete(route('tasks.destroy', $task))->assertForbidden();

        $this->assertNotSoftDeleted($task);
    }

    public function test_index_past_the_last_page_returns_an_empty_page(): void
    {
        Task::factory()->forTenant($this->firm)->createdBy($this->lead)->create();
        $this->webAs($this->lead, $this->firm);

        $this->withoutVite()->get('/tasks?page=5')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tasks.data', 0)
            ->where('tasks.current_page', 5)
            ->etc());
    }
}
