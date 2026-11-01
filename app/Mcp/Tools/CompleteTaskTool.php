<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('complete_task')]
#[Description('Marks an open task as done.')]
#[IsDestructive(false)]
final class CompleteTaskTool extends TenantTool
{
    protected function rules(): array
    {
        return ['task_id' => ['required', 'uuid']];
    }

    public function execute(Request $request, User $user, TaskService $tasks): Response
    {
        $task = Task::query()->findOrFail($this->id($request, 'task_id'));

        Gate::forUser($user)->authorize('update', $task);

        return $this->json($tasks->detail($tasks->complete($task), $user));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'task_id' => $schema->string()->format('uuid')->description('Task id from list_tasks.')->required(),
        ];
    }
}
