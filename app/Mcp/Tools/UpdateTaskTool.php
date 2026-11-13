<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Tasks\TaskPatchData;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update_task')]
#[Description('Changes the title, description, priority, due date or reminder of a task. Omitted fields stay as they are. Assignee: use assign_task.')]
#[IsDestructive]
final class UpdateTaskTool extends TenantTool
{
    protected function rules(): array
    {
        return ['task_id' => ['required', 'uuid']];
    }

    public function execute(Request $request, User $user, TaskService $tasks): Response
    {
        $task = Task::query()->findOrFail($this->id($request, 'task_id'));

        Gate::forUser($user)->authorize('update', $task);

        $data = TaskPatchData::validateAndCreate(Arr::only($request->all(), ['title', 'priority', 'description', 'due_at', 'remind_at']));

        return $this->json($tasks->detail($tasks->patch($task, $data, $user), $user));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'task_id' => $schema->string()->format('uuid')->description('Task id from list_tasks.')->required(),
            'title' => $schema->string()->max(200)->description('New title.'),
            'priority' => $schema->string()->enum(['low', 'normal', 'high'])->description('New priority.'),
            'description' => $schema->string()->max(5000)->nullable()->description('New description; null clears it.'),
            'due_at' => $schema->string()->nullable()->description('New due date-time, ISO 8601; null clears it.'),
            'remind_at' => $schema->string()->nullable()->description('New reminder date-time, ISO 8601, rounded up to the next slot (07:00, 12:00 or 19:00 Europe/Bratislava); null clears it.'),
        ];
    }
}
