<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskRunStatusEnum;
use App\Models\Task;
use App\Models\TaskRun;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskRun>
 */
final class TaskRunFactory extends Factory
{
    /** @var class-string<TaskRun> */
    protected $model = TaskRun::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'task_id' => Task::factory(),
            'started_by_user_id' => User::factory(),
            'status' => TaskRunStatusEnum::Queued,
            'closed_task' => false,
            'actions' => [],
        ];
    }

    public function forTask(Task $task): static
    {
        return $this->state(fn () => ['task_id' => $task->id, 'tenant_id' => $task->tenant_id ?? Tenant::factory()]);
    }

    public function startedBy(User $user): static
    {
        return $this->state(fn () => ['started_by_user_id' => $user->id]);
    }

    public function withStatus(TaskRunStatusEnum $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
