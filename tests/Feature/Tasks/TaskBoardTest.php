<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\PermissionEnum;
use App\Enums\TaskPriorityEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Media;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskRun;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskBoardTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->admin = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Admin', $this->admin);
    }

    /** @return array<string, array{status: string, total: int, has_more: bool, tasks: list<array<string, mixed>>}> */
    private function columns(string $query = ''): array
    {
        /** @var list<array{status: string, total: int, has_more: bool, tasks: list<array<string, mixed>>}> $columns */
        $columns = $this->apiAs($this->admin, $this->alpha)->getJson('/api/tasks/board'.$query)->assertOk()->json('columns');

        return array_column($columns, null, 'status');
    }

    public function test_board_returns_five_columns_in_board_order_with_totals(): void
    {
        Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->count(2)->create();
        Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create(['status' => TaskStatusEnum::Waiting]);
        Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->done()->create();
        Task::factory()->forTenant($this->beta)->createdBy($this->admin)->create();

        $columns = $this->columns();

        $this->assertSame(['open', 'in_progress', 'waiting', 'nice_to_have', 'done'], array_keys($columns));
        $this->assertSame([2, 0, 1, 0, 1], array_column($columns, 'total'));
        $this->assertCount(2, $columns['open']['tasks']);
        $this->assertFalse($columns['open']['has_more']);
    }

    public function test_all_scope_spans_companies_and_respects_visibility(): void
    {
        $other = $this->member($this->alpha, 'Admin');
        Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();
        Task::factory()->forTenant($this->beta)->createdBy($this->admin)->create();
        Task::factory()->global()->createdBy($other)->create();

        $this->assertSame(1, $this->columns()['open']['total']);

        /** @var list<array{status: string, total: int}> $all */
        $all = $this->apiAs($this->admin, $this->alpha)->getJson('/api/tasks/board?scope=all')->assertOk()->json('columns');
        $this->assertSame(2, $all[0]['total']);
    }

    public function test_cards_are_ordered_by_priority_then_due_date_nulls_last(): void
    {
        $make = fn (string $title, TaskPriorityEnum $priority, ?string $due) => Task::factory()->forTenant($this->alpha)->createdBy($this->admin)
            ->create(['title' => $title, 'priority' => $priority, 'due_at' => $due]);
        $make('normal-late', TaskPriorityEnum::Normal, '2026-12-20 10:00:00');
        $make('low-soon', TaskPriorityEnum::Low, '2026-12-01 10:00:00');
        $make('high-none', TaskPriorityEnum::High, null);
        $make('normal-soon', TaskPriorityEnum::Normal, '2026-12-05 10:00:00');
        $make('normal-none', TaskPriorityEnum::Normal, null);
        $make('high-soon', TaskPriorityEnum::High, '2026-12-10 10:00:00');

        $titles = array_column($this->columns()['open']['tasks'], 'title');

        $this->assertSame(['high-soon', 'high-none', 'normal-soon', 'normal-late', 'normal-none', 'low-soon'], $titles);
    }

    public function test_done_column_shows_the_newest_completed_first(): void
    {
        $make = fn (string $title, string $completed) => Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->done()
            ->create(['title' => $title, 'completed_at' => $completed]);
        $make('old', '2026-10-01 10:00:00');
        $make('new', '2026-11-01 10:00:00');
        $make('mid', '2026-10-15 10:00:00');

        $this->assertSame(['new', 'mid', 'old'], array_column($this->columns()['done']['tasks'], 'title'));
    }

    public function test_column_is_capped_at_the_limit_and_reports_has_more(): void
    {
        config(['tasks.board.column_limit' => 3]);
        Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->count(4)->create();
        Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->count(3)->create(['status' => TaskStatusEnum::Waiting]);

        $columns = $this->columns();

        $this->assertCount(3, $columns['open']['tasks']);
        $this->assertSame(4, $columns['open']['total']);
        $this->assertTrue($columns['open']['has_more']);
        $this->assertCount(3, $columns['waiting']['tasks']);
        $this->assertFalse($columns['waiting']['has_more']);
    }

    public function test_board_applies_list_filters_but_ignores_status_sort_and_paging(): void
    {
        Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create(['title' => 'Fix roof', 'priority' => TaskPriorityEnum::High]);
        Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create(['title' => 'Mow lawn']);
        Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create(['title' => 'Fix gate', 'status' => TaskStatusEnum::Waiting, 'priority' => TaskPriorityEnum::High]);

        $columns = $this->columns('?filter[priority]=high&filter[status]=done&sort=bogus&page=9&per_page=1');

        $this->assertSame(1, $columns['open']['total']);
        $this->assertSame(1, $columns['waiting']['total']);
        $this->assertSame(['Fix roof'], array_column($columns['open']['tasks'], 'title'));
        $this->assertSame(1, $this->columns('?filter[search]=lawn')['open']['total']);
    }

    public function test_board_needs_view_tasks(): void
    {
        $nobody = $this->member($this->alpha, 'Účtovníčka');
        $nobody->roles()->detach();
        $this->apiAs($nobody, $this->alpha)->getJson('/api/tasks/board')->assertForbidden();
    }

    public function test_card_fields_count_human_comments_and_attachments_only(): void
    {
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();
        TaskComment::factory()->forTask($task)->by($this->admin)->count(2)->create();
        $run = TaskRun::factory()->forTask($task)->startedBy($this->admin)->create();
        TaskComment::factory()->forTask($task)->byAi($run)->create();
        Media::query()->create([
            'tenant_id' => $this->alpha->id, 'model_type' => $task->getMorphClass(), 'model_id' => $task->id,
            'collection_name' => Task::ATTACHMENTS_COLLECTION, 'name' => 'a', 'file_name' => 'a.pdf', 'mime_type' => 'application/pdf',
            'disk' => 'local', 'size' => 10, 'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => [], 'responsive_images' => [],
        ]);

        $card = $this->columns()['open']['tasks'][0];

        $this->assertSame(2, $card['comments_count']);
        $this->assertSame(1, $card['attachments_count']);
        $this->assertTrue($card['can_update']);
        $this->assertTrue($card['is_active']);
    }

    public function test_comment_count_is_correct_across_companies_and_in_the_table_list(): void
    {
        $task = Task::factory()->forTenant($this->beta)->createdBy($this->admin)->create();
        TaskComment::factory()->forTask($task)->by($this->admin)->count(3)->create();

        /** @var list<array{status: string, tasks: list<array{comments_count: int}>}> $columns */
        $columns = $this->apiAs($this->admin, $this->alpha)->getJson('/api/tasks/board?scope=all')->assertOk()->json('columns');
        $this->assertSame(3, $columns[0]['tasks'][0]['comments_count']);

        $this->assertSame(3, $this->apiAs($this->admin, $this->alpha)->getJson('/api/tasks?scope=all')->assertOk()->json('data.0.comments_count'));
    }

    public function test_can_update_follows_the_edit_permission_and_personal_task_ownership(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);
        Role::inTenant($this->alpha->id)->where('name', 'Sekretárka')->firstOrFail()->revokePermissionTo(PermissionEnum::EditTasks->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $mine = Task::factory()->assignedTo($this->membershipOf($secretary, $this->alpha))->createdBy($this->admin)->create();
        $personal = Task::factory()->global()->createdBy($secretary)->create();

        /** @var list<array{id: string, can_update: bool}> $rows */
        $rows = $this->apiAs($secretary, $this->alpha)->getJson('/api/tasks')->assertOk()->json('data');
        $byId = array_column($rows, 'can_update', 'id');

        $this->assertFalse($byId[$mine->id]);
        $this->assertTrue($byId[$personal->id]);
    }

    public function test_web_view_param_picks_the_shape_and_the_last_view_is_remembered(): void
    {
        Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->alpha);

        $this->withoutVite()->get('/tasks')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'table')->has('tasks.data', 1)->where('board', null)->etc());

        $this->withoutVite()->get('/tasks?view=board')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'board')->where('tasks', null)->has('board.columns', 5)->etc());

        $this->withoutVite()->get('/tasks')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'board')->has('board.columns', 5)->etc());

        $this->withoutVite()->get('/tasks?view=nonsense')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'board')->etc());

        $this->withoutVite()->get('/tasks?view=table')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('view', 'table')->etc());
    }
}
