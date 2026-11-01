<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Data\Ai\AssistantActionData;
use App\Enums\AssistantActionTypeEnum;
use Illuminate\Support\Str;

/**
 * What the server itself did during one assistant request. Only ids returned by our own tools are recorded,
 * and links come from a fixed allowlist of in-app paths, so nothing the model writes can reach the reply.
 */
final class AssistantTurn
{
    private const array WRITES = [
        'create_task' => [AssistantActionTypeEnum::Created, 'task', '/tasks/'],
        'update_task' => [AssistantActionTypeEnum::Updated, 'task', '/tasks/'],
        'complete_task' => [AssistantActionTypeEnum::Updated, 'task', '/tasks/'],
        'assign_task' => [AssistantActionTypeEnum::Updated, 'task', '/tasks/'],
        'create_expense' => [AssistantActionTypeEnum::Created, 'expense', '/expenses/'],
        'update_expense' => [AssistantActionTypeEnum::Updated, 'expense', '/expenses/'],
        'create_quote_draft' => [AssistantActionTypeEnum::Created, 'quote', '/quotes/'],
        'update_quote_draft' => [AssistantActionTypeEnum::Updated, 'quote', '/quotes/'],
        'create_invoice_draft' => [AssistantActionTypeEnum::Created, 'invoice', '/invoices/'],
        'update_invoice_draft' => [AssistantActionTypeEnum::Updated, 'invoice', '/invoices/'],
    ];

    private float $deadline;

    /** @var list<string> */
    private array $pendingActionIds = [];

    /** @var array<string, AssistantActionData> */
    private array $writes = [];

    public function __construct(?float $budgetSeconds = null)
    {
        $configured = config('ai.assistant.turn_seconds', 45);

        $this->deadline = microtime(true) + ($budgetSeconds ?? (is_numeric($configured) ? (float) $configured : 45.0));
    }

    public function expired(): bool
    {
        return microtime(true) > $this->deadline;
    }

    public function hasEffects(): bool
    {
        return $this->writes !== [] || $this->pendingActionIds !== [];
    }

    public function observe(string $tool, string $result): void
    {
        $data = json_decode($result, true);

        if (! is_array($data)) {
            return;
        }

        if ($tool === 'prepare_action') {
            $id = data_get($data, 'pending_action.id');

            if (is_string($id) && Str::isUuid($id) && ! in_array($id, $this->pendingActionIds, true)) {
                $this->pendingActionIds[] = $id;
            }

            return;
        }

        $id = $data['id'] ?? null;

        if (isset(self::WRITES[$tool]) && is_string($id) && Str::isUuid($id)) {
            [$type, $noun, $path] = self::WRITES[$tool];

            $this->writes[$path.$id] = new AssistantActionData($type, __('app.assistant_noun_'.$noun), $path.$id);
        }
    }

    /** @return list<string> */
    public function pendingActionIds(): array
    {
        return $this->pendingActionIds;
    }

    /** @return list<AssistantActionData> */
    public function actions(): array
    {
        $actions = array_values($this->writes);
        $extra = count($this->pendingActionIds) - 1;

        if ($extra > 0) {
            $actions[] = new AssistantActionData(AssistantActionTypeEnum::Open, __('app.assistant_more_pending', ['count' => $extra]), '/pending-actions');
        }

        return $actions;
    }
}
