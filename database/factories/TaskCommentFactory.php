<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskCommentAuthorEnum;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskComment>
 */
final class TaskCommentFactory extends Factory
{
    /** @var class-string<TaskComment> */
    protected $model = TaskComment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'author_kind' => TaskCommentAuthorEnum::User,
            'task_run_id' => null,
            'answers_comment_id' => null,
            'body' => fake()->sentence(),
        ];
    }

    public function forTask(Task $task): static
    {
        return $this->state(fn () => ['task_id' => $task->id, 'tenant_id' => $task->tenant_id]);
    }

    public function by(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function byAi(TaskRun $run): static
    {
        return $this->state(fn () => [
            'author_kind' => TaskCommentAuthorEnum::Ai,
            'task_run_id' => $run->id,
            'user_id' => $run->started_by_user_id,
        ]);
    }
}
