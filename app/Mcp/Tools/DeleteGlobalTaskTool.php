<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\User;
use App\Services\TaskService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_global_task')]
#[Description('Deletes one of the user\'s own global tasks immediately, with no confirmation step. Firm tasks are deleted with prepare_action delete_task instead.')]
#[IsDestructive]
final class DeleteGlobalTaskTool extends CleanMasterTool
{
    public function handle(Request $request, TaskService $tasks): Response
    {
        return $this->guard($request, null, function (User $user) use ($request, $tasks): Response {
            $request->validate(['task_id' => ['required', 'uuid']]);

            $task = $tasks->findForSystem($request->string('task_id')->toString());

            if (! $task->isGlobal() || $task->created_by_user_id !== $user->id) {
                throw new ModelNotFoundException;
            }

            Gate::forUser($user)->authorize('delete', $task);

            $tasks->delete($task);
            $this->auditExtra = ['executed' => true, 'task_id' => $task->id];

            return $this->json(['executed' => true, 'message' => __('app.pending_action_done_delete_global_task')]);
        });
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->format('uuid')->description('Id of your own global task.')->required(),
        ];
    }
}
