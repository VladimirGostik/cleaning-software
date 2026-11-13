<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Data\Ai\AssistantActionData;
use App\Enums\ActionChannelEnum;
use App\Enums\AssistantActionTypeEnum;
use App\Enums\TaskRunOutcomeEnum;
use App\Exceptions\AssistantTimeBudgetExceeded;
use App\Services\Ai\Guard\PromptGuard;
use Illuminate\Support\Str;

/**
 * What the server itself did during one assistant request or task run. Only ids returned by our own tools are recorded,
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

    private const array FILE_TURN_WRITES = ['create_expense', 'create_task', 'create_contact', 'create_quote_draft', 'create_invoice_draft', 'prepare_action'];

    public readonly string $nonce;

    private float $deadline;

    private bool $restricted = false;

    private bool $deadlineAnnounced = false;

    private int $writeAttempts = 0;

    private int $prepareAttempts = 0;

    private int $refusedWrites = 0;

    private int $fileWriteAttempts = 0;

    private ?TaskRunOutcomeEnum $outcome = null;

    private ?string $outcomeMessage = null;

    /** @var list<string> */
    private array $blockedTools = [];

    /** @var list<string> */
    private array $pendingActionIds = [];

    /** @var array<string, AssistantActionData> */
    private array $writes = [];

    /** @var array<string, array{kind: string, id: string, company_id: string, label: string, numbers: list<int>}> */
    private array $createdRecords = [];

    public function __construct(
        ?float $budgetSeconds = null,
        public readonly ActionChannelEnum $channel = ActionChannelEnum::Assistant,
        public readonly ?string $pinnedCompanyId = null,
        public readonly ?string $taskRunId = null,
        public readonly int $fileCount = 0,
    ) {
        $this->nonce = bin2hex(random_bytes(8));
        $configured = config('ai.assistant.turn_seconds', 20);

        $this->deadline = microtime(true) + ($budgetSeconds ?? (is_numeric($configured) ? (float) $configured : 20.0));
    }

    public function restrict(): void
    {
        $this->restricted = true;
    }

    public function isRestricted(): bool
    {
        return $this->restricted;
    }

    /** Fails closed once anything suspicious was seen in this turn, and caps how much one turn can change. */
    public function refusalForWrite(string $tool): ?string
    {
        if ($this->restricted) {
            $this->blockedTools[] = $tool;

            return $this->refuseWrite(__('app.assistant_guard_blocked'));
        }

        if ($this->fileCount > 0) {
            if (! in_array($tool, self::FILE_TURN_WRITES, true)) {
                return $this->refuseWrite(__('app.assistant_file_write_blocked'));
            }

            if (++$this->fileWriteAttempts > config()->integer('ai.assistant.attachments.max_writes')) {
                return $this->refuseWrite(__('app.assistant_guard_limit'));
            }
        }

        if ($tool === 'prepare_action') {
            if (++$this->prepareAttempts > config()->integer('ai.guard.max_prepares_per_turn', 5)) {
                return $this->refuseWrite(__('app.assistant_guard_limit'));
            }

            return null;
        }

        if (++$this->writeAttempts > config()->integer('ai.guard.max_writes_per_turn', 10)) {
            return $this->refuseWrite(__('app.assistant_guard_limit'));
        }

        return null;
    }

    /** A write that never ran because the time budget was spent. */
    public function refuseWriteForBudget(): string
    {
        return $this->refuseWrite(__('app.assistant_time_budget'));
    }

    /** Writes refused by a cap, the restriction or the time budget: the work the model reports as done may be incomplete. */
    public function refusedWrites(): int
    {
        return $this->refusedWrites;
    }

    /** The first report wins, so a later one cannot undo a decision the server already acted on. */
    public function reportOutcome(TaskRunOutcomeEnum $outcome, string $message): bool
    {
        if ($this->outcome !== null) {
            return false;
        }

        $this->outcome = $outcome;
        $this->outcomeMessage = $message;

        return true;
    }

    public function outcome(): ?TaskRunOutcomeEnum
    {
        return $this->outcome;
    }

    public function outcomeMessage(): ?string
    {
        return $this->outcomeMessage;
    }

    private function refuseWrite(string $message): string
    {
        $this->refusedWrites++;

        return $message;
    }

    public function securityNotice(): ?string
    {
        if (! $this->restricted) {
            return null;
        }

        return __($this->blockedTools === [] ? 'app.assistant_guard_notice' : 'app.assistant_guard_notice_blocked');
    }

    public function expired(): bool
    {
        return microtime(true) > $this->deadline;
    }

    /** The first call past the deadline tells the model to answer; any later one ends the turn, so it stays under the 60 s request limit. */
    public function guardDeadline(): bool
    {
        if (! $this->expired()) {
            return false;
        }

        if ($this->deadlineAnnounced) {
            throw new AssistantTimeBudgetExceeded;
        }

        $this->deadlineAnnounced = true;

        return true;
    }

    public function hasEffects(): bool
    {
        return $this->writes !== [] || $this->pendingActionIds !== [];
    }

    /** @param  list<int>  $fileNumbers */
    public function observe(string $tool, string $result, ?string $companyId = null, array $fileNumbers = []): void
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

        if ($tool === 'create_contact' || $tool === 'update_contact') {
            $this->observeContact($data, $companyId);

            return;
        }

        $id = $data['id'] ?? null;

        if (isset(self::WRITES[$tool]) && is_string($id) && Str::isUuid($id)) {
            [$type, $noun, $path] = self::WRITES[$tool];

            $this->writes[$path.$id] = new AssistantActionData($type, __('app.assistant_noun_'.$noun), $path.$id, $companyId);

            if ($companyId !== null && in_array($tool, ['create_expense', 'create_task'], true)) {
                $label = $data['title'] ?? $data['supplier_name'] ?? $data['description'] ?? null;

                $this->createdRecords[$path.$id] = [
                    'kind' => $noun,
                    'id' => $id,
                    'company_id' => $companyId,
                    'label' => Str::limit(PromptGuard::stripInvisible(is_string($label) ? $label : ''), 60),
                    'numbers' => $fileNumbers,
                ];
            }
        }
    }

    /** @param  array<mixed>  $data */
    private function observeContact(array $data, ?string $companyId): void
    {
        $type = data_get($data, 'owner.type');
        $id = data_get($data, 'owner.id');

        if (! is_string($id) || ! Str::isUuid($id) || ! in_array($type, ['object', 'client'], true)) {
            return;
        }

        $path = ($type === 'object' ? '/objects/' : '/clients/').$id;

        $this->writes[$path] = new AssistantActionData(AssistantActionTypeEnum::Updated, __('app.assistant_noun_'.$type), $path, $companyId);
    }

    /** @return list<array{kind: string, id: string, company_id: string, label: string, numbers: list<int>}> */
    public function createdRecords(): array
    {
        return array_values($this->createdRecords);
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
