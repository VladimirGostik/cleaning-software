<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Tasks\TaskUpsertData;
use App\Enums\TaskSourceEnum;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_task')]
#[Description('Creates an open task in the company. Optionally link it to a record (taskable_type + taskable_id) and assign it to a member.')]
#[IsDestructive(false)]
final class CreateTaskTool extends TenantTool
{
    public function execute(Request $request, User $user, string $companyId, TaskService $tasks): Response
    {
        $data = TaskUpsertData::validateAndCreate([
            'title' => $request->get('title'),
            'priority' => $request->get('priority', 'normal'),
            'description' => $request->get('description'),
            'due_at' => $request->get('due_at'),
            'remind_at' => $request->get('remind_at'),
            'assigned_membership_id' => $request->get('assigned_membership_id'),
            'taskable_type' => $request->get('taskable_type'),
            'taskable_id' => $request->get('taskable_id'),
            'tenant_id' => $companyId,
            'source' => TaskSourceEnum::Mcp->value,
        ]);

        $tasks->assertMayCreate($data, $user);

        return $this->json($tasks->detail($tasks->create($data, $user), $user));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'title' => $schema->string()->max(200)->description('Short task title.')->required(),
            'priority' => $schema->string()->enum(['low', 'normal', 'high'])->description('Defaults to normal.'),
            'description' => $schema->string()->max(5000)->description('Longer description.'),
            'due_at' => $schema->string()->description('Due date-time, ISO 8601.'),
            'remind_at' => $schema->string()->description('Reminder date-time, ISO 8601. Rounded up to the next slot: 07:00, 12:00 or 19:00 Europe/Bratislava.'),
            'assigned_membership_id' => $schema->string()->format('uuid')->description('Membership of the assignee in this company.'),
            'taskable_type' => $schema->string()->enum(array_keys(Task::TASKABLE_TYPES))->description('Kind of record the task refers to.'),
            'taskable_id' => $schema->string()->format('uuid')->description('Id of that record; required with taskable_type.'),
        ];
    }
}
