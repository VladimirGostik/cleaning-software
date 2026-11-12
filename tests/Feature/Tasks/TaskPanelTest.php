<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskPanelTest extends TestCase
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

    public function test_index_with_a_visible_task_returns_the_panel_props(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->firm);

        $this->withoutVite()->get(route('tasks.index', ['task' => $task->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tasks/Index', false)
            ->where('selected_task.id', $task->id)
            ->has('selected_attachments')
            ->has('selected_thread')
            ->has('tasks.data', 1));
    }

    public function test_index_without_task_has_null_panel_props(): void
    {
        $this->webAs($this->admin, $this->firm);

        $this->withoutVite()->get(route('tasks.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('selected_task', null)
            ->where('selected_attachments', null)
            ->where('selected_thread', null));
    }

    public function test_foreign_invisible_and_malformed_task_ids_give_null_props(): void
    {
        $foreign = Task::factory()->forTenant($this->firm('Beta'))->createdBy($this->admin)->create();
        $secretary = $this->member($this->firm, 'Sekretárka');
        $hidden = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($secretary, $this->firm);

        foreach ([$foreign->id, $hidden->id, 'not-a-uuid'] as $id) {
            $this->withoutVite()->get(route('tasks.index', ['task' => $id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected_task', null)
                ->where('selected_attachments', null)
                ->where('selected_thread', null));
        }
    }

    public function test_show_redirects_to_the_panel_url(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->firm);

        $this->get(route('tasks.show', $task))->assertRedirect(route('tasks.index', ['task' => $task->id]));
    }

    public function test_show_redirect_keeps_the_authorization(): void
    {
        $secretary = $this->member($this->firm, 'Sekretárka');
        $hidden = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($secretary, $this->firm);

        $this->get(route('tasks.show', $hidden))->assertForbidden();
    }

    public function test_partial_reload_for_the_panel_skips_the_list(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $full = $this->webAs($this->admin, $this->firm)->withoutVite()->get(route('tasks.index', ['task' => $task->id]))->assertOk();

        $response = $this->webAs($this->admin, $this->firm)->withoutVite()->get(route('tasks.index', ['task' => $task->id]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => data_get($full->viewData('page'), 'version'),
            'X-Inertia-Partial-Component' => 'Tasks/Index',
            'X-Inertia-Partial-Data' => 'selected_task',
        ])->assertOk();

        $props = (array) $response->json('props');
        $this->assertArrayHasKey('selected_task', $props);
        $this->assertArrayNotHasKey('tasks', $props);
        $this->assertArrayNotHasKey('context', $props);
    }

    public function test_store_redirects_to_the_new_task_panel_and_update_goes_back(): void
    {
        $this->webAs($this->admin, $this->firm);

        $this->post('/tasks', ['title' => 'New', 'priority' => 'low'])
            ->assertRedirect(route('tasks.index', ['task' => Task::query()->sole()->id]));

        $task = Task::query()->sole();
        $this->from('/tasks?task='.$task->id)->put(route('tasks.update', $task), ['title' => 'Renamed', 'priority' => 'low'])
            ->assertRedirect('/tasks?task='.$task->id);
    }

    public function test_destroy_redirects_to_the_plain_list(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->firm);

        $this->from('/tasks?task='.$task->id)->delete(route('tasks.destroy', $task))->assertRedirect(route('tasks.index'));
    }
}
