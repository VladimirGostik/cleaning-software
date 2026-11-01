<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_tasks')]
#[Description('Pages through the tasks of one company plus the user\'s own global tasks. Filter by search, status or priority.')]
#[IsReadOnly]
final class ListTasksTool extends ListTool
{
    protected function filterKeys(): array
    {
        return ['search', 'status', 'priority', 'kind', 'assigned_membership_id'];
    }

    public function execute(User $user, TaskService $tasks): Response
    {
        Gate::forUser($user)->authorize('viewAny', Task::class);

        return $this->json($this->pageOf($tasks->paginate(request(), $user)));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'search' => $schema->string()->description('Title or description contains.'),
            'status' => $schema->string()->enum(['open', 'done', 'cancelled'])->description('Task status.'),
            'priority' => $schema->string()->enum(['low', 'normal', 'high'])->description('Task priority.'),
            'kind' => $schema->string()->enum(['firm', 'global'])->description('Firm tasks or the user\'s global tasks only.'),
            'assigned_membership_id' => $schema->string()->format('uuid')->description('Only tasks of this assignee.'),
            ...$this->pagingProperties($schema),
        ];
    }
}
