<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Tasks\TaskAssignData;
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

#[Name('assign_task')]
#[Description('Assigns a firm task to a member of the company, or unassigns it with a null assignee. Global tasks cannot be assigned.')]
#[IsDestructive(false)]
final class AssignTaskTool extends TenantTool
{
    protected function rules(): array
    {
        return ['task_id' => ['required', 'uuid']];
    }

    public function execute(Request $request, User $user, TaskService $tasks): Response
    {
        $task = Task::query()->findOrFail($this->id($request, 'task_id'));

        Gate::forUser($user)->authorize('assign', $task);

        $data = TaskAssignData::validateAndCreate(['assigned_membership_id' => $request->get('assigned_membership_id')]);

        return $this->json($tasks->detail($tasks->assign($task, $data, $user), $user));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'task_id' => $schema->string()->format('uuid')->description('Task id from list_tasks.')->required(),
            'assigned_membership_id' => $schema->string()->format('uuid')->nullable()->description('Membership id of the new assignee; null unassigns.')->required(),
        ];
    }
}
