<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TaskStatusMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_rows_become_done_and_other_rows_are_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $open = $this->makeTask($tenant, $user);
        $done = $this->makeTask($tenant, $user, ['status' => 'done', 'completed_at' => '2026-10-01 10:00:00']);
        $cancelled = $this->makeTask($tenant, $user);
        $trashed = $this->makeTask($tenant, $user);
        $withCompleted = $this->makeTask($tenant, $user);
        DB::table('tasks')->whereIn('id', [$cancelled->id, $trashed->id, $withCompleted->id])->update(['status' => 'cancelled', 'updated_at' => '2026-10-05 08:00:00']);
        DB::table('tasks')->where('id', $withCompleted->id)->update(['completed_at' => '2026-10-02 09:00:00']);
        DB::table('tasks')->where('id', $trashed->id)->update(['deleted_at' => now()]);
        $openBefore = (array) DB::table('tasks')->where('id', $open->id)->first();
        $doneBefore = (array) DB::table('tasks')->where('id', $done->id)->first();

        $migration = require database_path('migrations/2026_11_13_000500_task_board_statuses.php');
        $this->assertIsObject($migration);
        $this->assertTrue(is_callable([$migration, 'up']));
        call_user_func([$migration, 'up']);

        $this->assertSame('done', DB::table('tasks')->where('id', $cancelled->id)->value('status'));
        $this->assertStringStartsWith('2026-10-05 08:00:00', $this->column('tasks', 'completed_at', 'id', $cancelled->id));
        $this->assertSame('done', DB::table('tasks')->where('id', $trashed->id)->value('status'));
        $this->assertNotNull(DB::table('tasks')->where('id', $trashed->id)->value('completed_at'));
        $this->assertStringStartsWith('2026-10-02 09:00:00', $this->column('tasks', 'completed_at', 'id', $withCompleted->id));
        $this->assertSame($openBefore, (array) DB::table('tasks')->where('id', $open->id)->first());
        $this->assertSame($doneBefore, (array) DB::table('tasks')->where('id', $done->id)->first());
    }

    public function test_pending_reminder_index_covers_the_four_unfinished_statuses(): void
    {
        $definition = $this->column('pg_indexes', 'indexdef', 'indexname', 'tasks_pending_reminder_idx');

        foreach (['open', 'in_progress', 'waiting', 'nice_to_have'] as $status) {
            $this->assertStringContainsString("'{$status}'", $definition);
        }

        $this->assertStringNotContainsString("'done'", $definition);
    }

    /** @param  array<string, mixed>  $attributes */
    private function makeTask(Tenant $tenant, User $user, array $attributes = []): Task
    {
        return Task::factory()->forTenant($tenant)->createdBy($user)->create($attributes);
    }

    private function column(string $table, string $column, string $key, string $id): string
    {
        $value = DB::table($table)->where($key, $id)->value($column);

        return is_string($value) ? $value : '';
    }
}
