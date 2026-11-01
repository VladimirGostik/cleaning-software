<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Ai\RunsTasks;
use App\Enums\TaskRunStatusEnum;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskRun;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TaskRunService;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeTaskRunner;

trait CreatesTaskRunFixtures
{
    use CreatesMcpFixtures;

    protected Tenant $alpha;

    protected Tenant $beta;

    protected User $admin;

    protected FakeTaskRunner $runner;

    protected function setUpTaskRunFixtures(bool $fakeQueue = true): void
    {
        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->admin = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Admin', $this->admin);
        $this->runner = new FakeTaskRunner;
        $this->app->instance(RunsTasks::class, $this->runner);
        if ($fakeQueue) {
            Queue::fake();
        }
    }

    /** Run comments are written in the starter's locale, which the user factory sets to Slovak. */
    protected function notice(string $key): string
    {
        return __('app.'.$key, [], 'sk');
    }

    /**
     * @param  mixed  $rows  Decoded JSON list of rows.
     * @return mixed The value of `$key` in the row whose `$matchKey` equals `$matchValue`.
     */
    protected function rowValue(mixed $rows, string $matchKey, string $matchValue, string $key): mixed
    {
        foreach ((array) $rows as $row) {
            if (is_array($row) && ($row[$matchKey] ?? null) === $matchValue) {
                return $row[$key] ?? null;
            }
        }

        $this->fail("No row with {$matchKey} = {$matchValue}.");
    }

    protected function firmTask(?User $creator = null, ?Tenant $tenant = null): Task
    {
        return Task::factory()->forTenant($tenant ?? $this->alpha)->createdBy($creator ?? $this->admin)->create();
    }

    protected function globalTask(?User $creator = null): Task
    {
        return Task::factory()->global()->createdBy($creator ?? $this->admin)->create();
    }

    /** A queued run, as `start` leaves it. */
    protected function queuedRun(Task $task, User $starter, ?Tenant $company = null): TaskRun
    {
        return TaskRun::factory()->state([
            'task_id' => $task->id,
            'tenant_id' => $company->id ?? $task->tenant_id,
            'started_by_user_id' => $starter->id,
        ])->create();
    }

    /** What the queue worker does: no web request, no bound tenant or user. */
    protected function execute(TaskRun $run): TaskRun
    {
        $this->consoleContext();
        app(TaskRunService::class)->execute($run->id);

        return $run->refresh();
    }

    protected function finishedRun(Task $task, User $starter, TaskRunStatusEnum $status, ?Tenant $company = null): TaskRun
    {
        return TaskRun::factory()->state([
            'task_id' => $task->id,
            'tenant_id' => $company->id ?? $task->tenant_id,
            'started_by_user_id' => $starter->id,
            'status' => $status,
            'finished_at' => now(),
        ])->create();
    }

    protected function aiComment(Task $task, TaskRun $run, string $body = 'AI says hello'): TaskComment
    {
        return TaskComment::factory()->forTask($task)->byAi($run)->state(['body' => $body])->create();
    }

    protected function humanComment(Task $task, User $author, string $body = 'A comment'): TaskComment
    {
        return TaskComment::factory()->forTask($task)->by($author)->state(['body' => $body])->create();
    }

    protected function memberWith(Tenant $tenant, string $role, string ...$permissions): User
    {
        $user = $this->member($tenant, $role);

        if ($permissions !== []) {
            $this->grant($user, $tenant, ...$permissions);
        }

        return $user;
    }
}
