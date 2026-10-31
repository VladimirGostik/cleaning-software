<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\TaskPriorityEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Navigation\NavigationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskCrudTest extends TestCase
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

    public function test_index_lists_active_firm_tasks_and_own_global_tasks(): void
    {
        $firmTask = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create(['title' => 'Firm task']);
        $globalTask = Task::factory()->global()->createdBy($this->admin)->create(['title' => 'Global task']);
        Task::factory()->forTenant($this->firm('Beta'))->createdBy($this->admin)->create(['title' => 'Other firm']);
        $this->webAs($this->admin, $this->firm);

        $this->withoutVite()->get('/tasks')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tasks/Index', false)
            ->has('tasks.data', 2)
            ->has('context.companies', 1)
            ->where('context.companies.0.tenant_id', $this->firm->id)
            ->etc());

        $this->assertNotSame($firmTask->id, $globalTask->id);
    }

    public function test_index_filters_by_kind_and_status(): void
    {
        Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        Task::factory()->forTenant($this->firm)->createdBy($this->admin)->done()->create();
        Task::factory()->global()->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->firm);

        $this->withoutVite()->get('/tasks?filter[kind]=global')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tasks.data', 1)->where('tasks.data.0.is_global', true)->etc());
        $this->withoutVite()->get('/tasks?filter[status]=done')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tasks.data', 1)->where('tasks.data.0.status', 'done')->etc());
    }

    public function test_store_without_tenant_id_creates_a_firm_task_in_the_active_tenant(): void
    {
        $this->webAs($this->admin, $this->firm);

        $response = $this->post('/tasks', ['title' => 'Call the client', 'priority' => 'high', 'due_at' => '2026-12-01 10:00']);

        $task = Task::query()->sole();
        $response->assertRedirect(route('tasks.show', $task));
        $this->assertSame($this->firm->id, $task->tenant_id);
        $this->assertSame($this->admin->id, $task->created_by_user_id);
        $this->assertSame(TaskPriorityEnum::High, $task->priority);
        $this->assertSame(TaskStatusEnum::Open, $task->status);
    }

    public function test_store_with_explicit_null_tenant_creates_a_global_task(): void
    {
        $this->webAs($this->admin, $this->firm);

        $this->post('/tasks', ['title' => 'Personal', 'priority' => 'low', 'tenant_id' => null])->assertRedirect();

        $this->assertNull(Task::query()->sole()->tenant_id);
    }

    public function test_store_validates_title_and_priority(): void
    {
        $this->webAs($this->admin, $this->firm);

        $this->post('/tasks', ['title' => '', 'priority' => 'urgent'])->assertSessionHasErrors(['title', 'priority']);
        $this->post('/tasks', ['title' => str_repeat('x', 201), 'priority' => 'low'])->assertSessionHasErrors(['title']);
        $this->assertSame(0, Task::unscopedForSystem()->count());
    }

    public function test_store_links_a_subject_of_the_same_firm_only(): void
    {
        $own = Invoice::factory()->create(['tenant_id' => $this->firm->id]);
        $foreign = Invoice::factory()->create(['tenant_id' => $this->firm('Beta')->id]);
        $this->webAs($this->admin, $this->firm);

        $this->post('/tasks', ['title' => 'Chase', 'priority' => 'normal', 'taskable_type' => 'invoice', 'taskable_id' => $own->id])->assertRedirect();
        $this->post('/tasks', ['title' => 'Bad', 'priority' => 'normal', 'taskable_type' => 'invoice', 'taskable_id' => $foreign->id])
            ->assertSessionHasErrors(['taskable_id']);

        $task = Task::query()->sole();
        $this->assertSame(Invoice::class, $task->taskable_type);

        $this->withoutVite()->get(route('tasks.show', $task))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('task.subject.type', 'invoice')
            ->where('task.subject.id', $own->id)
            ->etc());
    }

    public function test_global_task_cannot_link_a_subject(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->firm->id]);
        $this->webAs($this->admin, $this->firm);

        $this->post('/tasks', ['title' => 'G', 'priority' => 'normal', 'tenant_id' => null, 'taskable_type' => 'invoice', 'taskable_id' => $invoice->id])
            ->assertSessionHasErrors(['taskable_type']);
    }

    public function test_subject_type_and_id_must_come_together(): void
    {
        $this->webAs($this->admin, $this->firm);

        $this->post('/tasks', ['title' => 'T', 'priority' => 'normal', 'taskable_type' => 'invoice'])->assertSessionHasErrors(['taskable_id']);
        $this->post('/tasks', ['title' => 'T', 'priority' => 'normal', 'taskable_type' => 'user', 'taskable_id' => fake()->uuid()])->assertSessionHasErrors(['taskable_type']);
    }

    public function test_show_exposes_detail_and_capabilities(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create(['description' => 'Details']);
        $this->webAs($this->admin, $this->firm);

        $this->withoutVite()->get(route('tasks.show', $task))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Tasks/Show', false)
            ->where('task.id', $task->id)
            ->where('task.description', 'Details')
            ->where('task.created_by_name', $this->admin->name)
            ->where('task.can', ['update' => true, 'delete' => true, 'assign' => true])
            ->etc());
    }

    public function test_update_changes_fields_but_not_tenant_or_assignee(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $other = $this->firm('Beta');
        $this->webAs($this->admin, $this->firm);

        $this->put(route('tasks.update', $task), [
            'title' => 'Renamed', 'priority' => 'high', 'tenant_id' => $other->id,
        ])->assertRedirect(route('tasks.show', $task));

        $task->refresh();
        $this->assertSame('Renamed', $task->title);
        $this->assertSame($this->firm->id, $task->tenant_id);
    }

    public function test_changing_remind_at_rearms_the_reminder(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create([
            'remind_at' => now()->subHour(), 'reminded_at' => now()->subMinutes(30),
        ]);
        $this->webAs($this->admin, $this->firm);

        $this->put(route('tasks.update', $task), ['title' => $task->title, 'priority' => 'normal', 'remind_at' => now()->addDay()->toDateTimeString()])->assertRedirect();

        $this->assertNull($task->refresh()->reminded_at);
    }

    public function test_complete_and_reopen_transitions(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->firm);

        $this->post(route('tasks.complete', $task))->assertRedirect();
        $task->refresh();
        $this->assertSame(TaskStatusEnum::Done, $task->status);
        $this->assertNotNull($task->completed_at);

        $this->postJson(route('tasks.complete', $task))->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->post(route('tasks.reopen', $task))->assertRedirect();
        $task->refresh();
        $this->assertSame(TaskStatusEnum::Open, $task->status);
        $this->assertNull($task->completed_at);

        $this->postJson(route('tasks.reopen', $task))->assertUnprocessable();
    }

    public function test_destroy_soft_deletes(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->firm);

        $this->delete(route('tasks.destroy', $task))->assertRedirect(route('tasks.index'));

        $this->assertSoftDeleted($task);
        $this->get(route('tasks.show', $task))->assertNotFound();
    }

    public function test_user_without_task_permissions_is_forbidden(): void
    {
        $cleaner = $this->member($this->firm, 'Interná upratovačka');
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($cleaner, $this->firm);

        $this->get('/tasks')->assertForbidden();
        $this->post('/tasks', ['title' => 'X', 'priority' => 'low'])->assertForbidden();
        $this->get(route('tasks.show', $task))->assertForbidden();
    }

    public function test_task_of_another_firm_is_not_found(): void
    {
        $beta = $this->firm('Beta');
        $task = Task::factory()->forTenant($beta)->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->firm);

        $this->get(route('tasks.show', $task))->assertNotFound();
        $this->put(route('tasks.update', $task), ['title' => 'x', 'priority' => 'low'])->assertNotFound();
    }

    public function test_navigation_item_is_registered_for_task_viewers(): void
    {
        $this->bindTenant($this->firm);

        $navigation = app(NavigationRegistry::class)->forUser($this->admin);

        $this->assertContains('tasks.index', array_map(fn ($item) => $item->key, $navigation));
    }

    public function test_store_in_another_firm_redirects_to_the_index_with_the_flash(): void
    {
        $beta = $this->firm('Beta');
        $this->member($beta, 'Admin', $this->admin);
        $this->webAs($this->admin, $this->firm);

        $this->post('/tasks', ['title' => 'Elsewhere', 'priority' => 'low', 'tenant_id' => $beta->id])
            ->assertRedirect(route('tasks.index'))
            ->assertSessionHas('success');

        $this->assertSame($beta->id, Task::unscopedForSystem()->sole()->tenant_id);
    }

    public function test_global_task_activity_never_logs_title_or_description(): void
    {
        $this->webAs($this->admin, $this->firm);
        $this->post('/tasks', ['title' => 'Secret title', 'description' => 'Secret body', 'priority' => 'low', 'tenant_id' => null])->assertRedirect();
        $this->put(route('tasks.update', Task::query()->sole()), ['title' => 'Secret renamed', 'description' => 'Secret again', 'priority' => 'high'])->assertRedirect();

        $rows = Activity::query()->where('subject_id', Task::query()->sole()->id)->get();

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertStringNotContainsString('Secret', json_encode($row->properties, JSON_THROW_ON_ERROR).$row->attribute_changes);
        }
    }

    public function test_firm_task_activity_still_logs_the_title(): void
    {
        $this->webAs($this->admin, $this->firm);
        $this->post('/tasks', ['title' => 'Visible title', 'priority' => 'low'])->assertRedirect();

        $row = Activity::query()->where('subject_id', Task::query()->sole()->id)->sole();

        $this->assertStringContainsString('Visible title', json_encode($row->properties, JSON_THROW_ON_ERROR).$row->attribute_changes);
    }

    public function test_attaching_a_subject_needs_view_access_to_it(): void
    {
        $secretary = $this->member($this->firm, 'Sekretárka');
        $invoice = Invoice::factory()->create(['tenant_id' => $this->firm->id]);
        $client = Client::factory()->create(['tenant_id' => $this->firm->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $this->firm->id, 'client_id' => $client->id]);
        $this->webAs($secretary, $this->firm);

        $this->post('/tasks', ['title' => 'Peek', 'priority' => 'low', 'taskable_type' => 'invoice', 'taskable_id' => $invoice->id])->assertForbidden();
        $this->assertSame(0, Task::unscopedForSystem()->count());

        $this->post('/tasks', ['title' => 'Object', 'priority' => 'low', 'taskable_type' => 'cleaning_object', 'taskable_id' => $object->id])->assertRedirect();
        $this->withoutVite()->get(route('tasks.show', Task::query()->sole()))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('task.subject.type', 'cleaning_object')->where('task.subject.id', $object->id)->etc());
    }

    public function test_editing_keeps_an_unchanged_subject_the_editor_cannot_view(): void
    {
        $secretary = $this->member($this->firm, 'Sekretárka');
        $invoice = Invoice::factory()->create(['tenant_id' => $this->firm->id]);
        $other = Invoice::factory()->create(['tenant_id' => $this->firm->id]);
        $task = Task::factory()->forTenant($this->firm)->createdBy($secretary)->create(['taskable_type' => Invoice::class, 'taskable_id' => $invoice->id]);
        $this->webAs($secretary, $this->firm);

        $this->put(route('tasks.update', $task), ['title' => 'Renamed', 'priority' => 'low', 'taskable_type' => 'invoice', 'taskable_id' => $invoice->id])->assertRedirect();
        $this->put(route('tasks.update', $task), ['title' => 'Again', 'priority' => 'low', 'taskable_type' => 'invoice', 'taskable_id' => $other->id])->assertForbidden();

        $task->refresh();
        $this->assertSame('Renamed', $task->title);
        $this->assertSame($invoice->id, $task->taskable_id);
    }

    public function test_editing_title_only_does_not_rearm_a_reminder_with_seconds(): void
    {
        $remind = now()->addDay()->setSeconds(30);
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create(['remind_at' => $remind, 'reminded_at' => now()]);
        $this->webAs($this->admin, $this->firm);

        $this->put(route('tasks.update', $task), ['title' => 'Only title', 'priority' => 'normal', 'remind_at' => $remind->copy()->setSeconds(0)->toDateTimeString()])->assertRedirect();

        $this->assertNotNull($task->refresh()->reminded_at);
    }
}
