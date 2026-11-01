<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Data\Ai\ActionResultData;
use App\Data\Ai\PendingActionData;
use App\Data\Ai\PendingActionPreparedData;
use App\Data\Invoices\InvoiceIssueData;
use App\Enums\ActionChannelEnum;
use App\Enums\ConfirmationChannelEnum;
use App\Enums\PendingActionEnum;
use App\Enums\PendingActionStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PendingAction;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Services\ContractService;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use App\Services\TaskService;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use LogicException;
use Throwable;

final readonly class ActionConfirmationService
{
    public function __construct(
        private DatabaseManager $db,
        private ToolContext $context,
        private ToolAudit $audit,
        private InvoiceService $invoices,
        private QuoteService $quotes,
        private ContractService $contracts,
        private TaskService $tasks,
        private ExpenseService $expenses,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public function prepare(
        User $user,
        string $tenantId,
        PendingActionEnum $action,
        Model $subject,
        array $payload,
        ActionChannelEnum $channel,
    ): PendingActionPreparedData {
        $subjectClass = $action->subjectClass();

        if (! $subject instanceof $subjectClass) {
            throw ValidationException::withMessages(['subject_id' => [__('app.pending_action_subject_invalid')]]);
        }

        if ($subject->getAttribute('tenant_id') !== $tenantId) {
            throw ValidationException::withMessages(['subject_id' => [__('app.pending_action_subject_invalid')]]);
        }

        $this->context->run($user, $tenantId, function () use ($user, $action, $subject): void {
            Gate::forUser($user)->authorize($action->ability(), $subject);
        });

        $this->assertPreconditions($action, $subject);

        $described = $this->describe($action, $subject, $payload);
        $token = $channel === ActionChannelEnum::Mcp ? Str::random(40) : null;

        $pending = PendingAction::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'payload' => $payload,
            'payload_hash' => $this->hash($payload),
            'token_hash' => $token === null ? null : hash('sha256', $token),
            'subject_hash' => $this->fingerprint($subject),
            'preview' => $described['preview'],
            'subject_label' => $described['label'],
            'customer' => $described['customer'],
            'total' => $described['total'],
            'email' => $described['email'],
            'channel' => $channel,
            'status' => PendingActionStatusEnum::Pending,
            'expires_at' => now()->addMinutes(config()->integer('ai.pending_action_ttl_minutes', 10)),
        ]);

        return new PendingActionPreparedData(PendingActionData::fromModel($pending), $token);
    }

    public function resolveToken(User $user, string $token): PendingAction
    {
        $action = PendingAction::query()->where('token_hash', hash('sha256', $token))->first();

        if ($action === null || $action->user_id !== $user->id || ! $action->isPending() || $action->isExpired()) {
            throw ValidationException::withMessages(['token' => [__('app.pending_action_token_invalid')]]);
        }

        return $action;
    }

    public function execute(User $user, PendingAction $action, ConfirmationChannelEnum $via): ActionResultData
    {
        try {
            return $this->context->run($user, $action->tenant_id, fn (): ActionResultData => $this->db->transaction(
                fn (): ActionResultData => $this->executeLocked($user, $action, $via),
            ));
        } catch (Throwable $exception) {
            $this->audit->record($user, 'confirm', $action->tenant_id, $action->channel, [], 'error', [
                'pending_action_id' => $action->id,
                'action' => $action->action->value,
                'confirmed_via' => $via->value,
                'reason' => Str::limit($this->reason($exception), 200),
            ]);

            throw $exception;
        }
    }

    public function cancel(User $user, PendingAction $action): PendingAction
    {
        return $this->db->transaction(function () use ($user, $action): PendingAction {
            /** @var PendingAction $row */
            $row = PendingAction::query()->whereKey($action->id)->lockForUpdate()->firstOrFail();

            if (! $row->isPending()) {
                throw ValidationException::withMessages(['status' => [__('app.pending_action_not_pending')]]);
            }

            $row->update(['status' => PendingActionStatusEnum::Cancelled]);

            $this->audit->record($user, 'cancel', $row->tenant_id, $row->channel, [], 'ok', [
                'pending_action_id' => $row->id,
                'action' => $row->action->value,
            ]);

            return $row;
        });
    }

    /**
     * @param  list<string>|null  $tenantIds
     * @return Collection<int, PendingAction>
     */
    public function pendingFor(User $user, ?array $tenantIds = null): Collection
    {
        return PendingAction::query()
            ->with('tenant:id,name')
            ->where('user_id', $user->id)
            ->where('status', PendingActionStatusEnum::Pending->value)
            ->where('expires_at', '>', now())
            ->whereIn('tenant_id', $user->memberships()->where('is_active', true)->select('tenant_id'))
            ->when($tenantIds !== null, fn ($query) => $query->whereIn('tenant_id', $tenantIds))
            ->orderBy('expires_at')
            ->get();
    }

    public function expireStale(): int
    {
        return PendingAction::query()
            ->where('status', PendingActionStatusEnum::Pending->value)
            ->where('expires_at', '<=', now())
            ->update(['status' => PendingActionStatusEnum::Expired->value]);
    }

    private function executeLocked(User $user, PendingAction $action, ConfirmationChannelEnum $via): ActionResultData
    {
        /** @var PendingAction $row */
        $row = PendingAction::query()->whereKey($action->id)->lockForUpdate()->firstOrFail();

        $this->assertExecutable($user, $row, $via);

        $subject = $this->resolveSubject($row);

        if (! hash_equals($row->subject_hash, $this->fingerprint($subject))) {
            throw ValidationException::withMessages(['status' => [__('app.pending_action_subject_changed')]]);
        }

        Gate::forUser($user)->authorize($row->action->ability(), $subject);

        if (! hash_equals($row->payload_hash, $this->hash($row->payload))) {
            throw ValidationException::withMessages(['status' => [__('app.pending_action_payload_changed')]]);
        }

        $row->update([
            'status' => PendingActionStatusEnum::Executed,
            'executed_at' => now(),
            'confirmed_via' => $via,
        ]);

        $this->perform($row, $subject, $user);

        $this->audit->record($user, 'confirm', $row->tenant_id, $row->channel, [], 'ok', [
            'pending_action_id' => $row->id,
            'action' => $row->action->value,
            'subject_id' => $row->subject_id,
            'confirmed_via' => $via->value,
        ]);

        return new ActionResultData(
            action: $row->action,
            subject_label: $row->subject_label,
            url: $this->url($row, $subject),
            message: __('app.pending_action_done_'.$row->action->value),
        );
    }

    private function assertExecutable(User $user, PendingAction $row, ConfirmationChannelEnum $via): void
    {
        if ($row->user_id !== $user->id) {
            throw new AuthorizationException(__('app.pending_action_forbidden'));
        }

        if (! $row->isPending()) {
            throw ValidationException::withMessages(['status' => [__('app.pending_action_not_pending')]]);
        }

        if ($row->isExpired()) {
            throw ValidationException::withMessages(['status' => [__('app.pending_action_expired')]]);
        }

        if (! in_array($via, $row->allowedConfirmationChannels(), true)) {
            throw ValidationException::withMessages(['status' => [__('app.pending_action_app_only')]]);
        }

        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken && $token->can('mobile') && ! $user->can(PermissionEnum::AccessMobileApp->value)) {
            throw new AuthorizationException(__('app.mobile_forbidden'));
        }
    }

    private function resolveSubject(PendingAction $row): Model
    {
        $class = $row->action->subjectClass();

        if ($class === Task::class) {
            $task = $this->tasks->findForSystem($row->subject_id);

            if ($task->tenant_id !== $row->tenant_id) {
                throw (new ModelNotFoundException)->setModel(Task::class, [$row->subject_id]);
            }

            return $task;
        }

        return $class::query()->findOrFail($row->subject_id);
    }

    private function perform(PendingAction $row, Model $subject, User $user): void
    {
        match (true) {
            $row->action === PendingActionEnum::IssueInvoice && $subject instanceof Invoice => $this->invoices->issue($subject, InvoiceIssueData::from($row->payload), $user),
            $row->action === PendingActionEnum::SendInvoice && $subject instanceof Invoice => $this->invoices->send($subject),
            $row->action === PendingActionEnum::CancelInvoice && $subject instanceof Invoice => $this->invoices->cancel($subject, $user),
            $row->action === PendingActionEnum::DeleteInvoiceDraft && $subject instanceof Invoice => $this->invoices->delete($subject),
            $row->action === PendingActionEnum::SendQuote && $subject instanceof Quote => $this->quotes->send($subject),
            $row->action === PendingActionEnum::DeleteQuote && $subject instanceof Quote => $this->quotes->delete($subject),
            $row->action === PendingActionEnum::SignContract && $subject instanceof Contract => $this->contracts->sign($subject),
            $row->action === PendingActionEnum::DeleteTask && $subject instanceof Task => $this->tasks->delete($subject),
            $row->action === PendingActionEnum::DeleteExpense && $subject instanceof Expense => $this->expenses->delete($subject),
            default => throw new LogicException('Pending action does not match its subject.'),
        };
    }

    private function url(PendingAction $row, Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Invoice && $row->action !== PendingActionEnum::DeleteInvoiceDraft => route('invoices.show', $subject),
            $subject instanceof Quote && $row->action === PendingActionEnum::SendQuote => route('quotes.show', $subject),
            $subject instanceof Contract => route('contracts.show', $subject),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{label: string, preview: string, customer: string|null, total: string|null, email: string|null}
     */
    private function describe(PendingActionEnum $action, Model $subject, array $payload): array
    {
        if ($subject instanceof Invoice) {
            $number = $subject->number ?? __('app.pending_number_draft');
            $customer = $this->untrusted($subject->customer_name);
            $total = $subject->total.' '.$subject->currency->value;
            $email = $this->untrusted($subject->customer_email ?? '-');
            $params = [
                'number' => $this->untrusted(is_string($payload['number'] ?? null) ? $payload['number'] : __('app.pending_number_auto')),
                'customer' => $customer,
                'total' => $total,
                'due' => $subject->due_date->toDateString(),
                'email' => $email,
                'current' => $this->untrusted($number),
            ];

            return [
                'label' => $this->fill('app.pending_label_invoice', ['number' => $params['current'], 'customer' => $customer]),
                'preview' => $this->fill('app.pending_preview_'.$action->value, $params),
                'customer' => $customer,
                'total' => $total,
                'email' => $subject->customer_email === null ? null : $email,
            ];
        }

        if ($subject instanceof Quote) {
            $number = $this->untrusted($subject->number ?? __('app.pending_number_draft'));
            $customer = $this->untrusted($subject->client->name ?? $subject->customer_name ?? '-');
            $total = $subject->total.' '.$subject->currency->value;
            $email = $this->untrusted($subject->customer_email ?? '-');
            $params = ['number' => $number, 'customer' => $customer, 'total' => $total, 'email' => $email];

            return [
                'label' => $this->fill('app.pending_label_quote', ['number' => $number, 'customer' => $customer]),
                'preview' => $this->fill('app.pending_preview_'.$action->value, $params),
                'customer' => $customer,
                'total' => $total,
                'email' => $subject->customer_email === null ? null : $email,
            ];
        }

        if ($subject instanceof Contract) {
            $params = ['title' => $this->untrusted($subject->title), 'number' => $this->untrusted($subject->number ?? '-')];

            return [
                'label' => $this->fill('app.pending_label_contract', $params),
                'preview' => $this->fill('app.pending_preview_'.$action->value, $params),
                'customer' => null,
                'total' => null,
                'email' => null,
            ];
        }

        if ($subject instanceof Task) {
            if ($subject->isGlobal()) {
                throw ValidationException::withMessages(['subject_id' => [__('app.pending_action_global_task')]]);
            }

            $title = $this->untrusted($subject->title);

            return [
                'label' => $title,
                'preview' => $this->fill('app.pending_preview_'.$action->value, ['title' => $title]),
                'customer' => null,
                'total' => null,
                'email' => null,
            ];
        }

        if ($subject instanceof Expense) {
            $amount = $subject->amount.' '.$subject->currency->value;
            $params = [
                'supplier' => $this->untrusted($subject->supplier_name ?? '-'),
                'amount' => $amount,
                'date' => $subject->date->toDateString(),
            ];

            return [
                'label' => $this->fill('app.pending_label_expense', $params),
                'preview' => $this->fill('app.pending_preview_'.$action->value, $params),
                'customer' => null,
                'total' => $amount,
                'email' => null,
            ];
        }

        throw ValidationException::withMessages(['subject_id' => [__('app.pending_action_subject_invalid')]]);
    }

    private function assertPreconditions(PendingActionEnum $action, Model $subject): void
    {
        $failure = match (true) {
            $action === PendingActionEnum::CancelInvoice && $subject instanceof Invoice && ! $subject->canBeCancelled() => 'app.invoice_cannot_cancel',
            $action === PendingActionEnum::DeleteInvoiceDraft && $subject instanceof Invoice && ! $subject->isEditable() => 'app.invoice_not_editable',
            $action === PendingActionEnum::DeleteQuote && $subject instanceof Quote && ! $subject->isEditable() => 'app.quote_not_editable',
            $action === PendingActionEnum::SignContract && $subject instanceof Contract && ! $subject->canBeSigned() => 'app.contract_cannot_sign',
            default => null,
        };

        if ($failure !== null) {
            throw ValidationException::withMessages(['status' => [__($failure)]]);
        }
    }

    /**
     * Covers the fields the preview shows plus the line items, which can change without bumping the parent's `updated_at`.
     */
    private function fingerprint(Model $subject): string
    {
        $material = match (true) {
            $subject instanceof Invoice => [
                $subject->number, $subject->customer_name, $subject->customer_email, $subject->total, $subject->currency->value,
                $subject->due_date->toDateString(), $subject->status->value,
                $this->itemsChecksum($subject->items()),
            ],
            $subject instanceof Quote => [
                $subject->number, $subject->customer_name, $subject->customer_email, $subject->client_id, $subject->client()->value('name'), $subject->total,
                $subject->currency->value, $subject->status->value,
                $this->itemsChecksum($subject->items()),
            ],
            $subject instanceof Expense => [$subject->amount, $subject->currency->value, $subject->supplier_name, $subject->date->toDateString(), $subject->status->value],
            $subject instanceof Contract => [$subject->title, $subject->number, $subject->status->value],
            $subject instanceof Task => [$subject->title, $subject->status->value, $subject->tenant_id],
            default => [],
        };

        $updatedAt = $subject->getAttribute('updated_at');

        return hash('sha256', json_encode([
            $subject->getKey(),
            $updatedAt instanceof DateTimeInterface ? $updatedAt->format('Y-m-d H:i:s.u') : null,
            $material,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @template TRelated of Model
     * @template TParent of Model
     *
     * @param  HasMany<TRelated, TParent>  $items
     */
    private function itemsChecksum(HasMany $items): string
    {
        $rows = [];

        foreach ($items->getQuery()->orderBy('position')->orderBy('id')->get() as $item) {
            $rows[] = $item->only(['description', 'quantity', 'unit', 'unit_price', 'discount_percent', 'vat_rate', 'line_total']);
        }

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private function untrusted(string $value): string
    {
        $flat = trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $value));

        return '"'.str_replace('"', "'", Str::limit($flat, 80, '...')).'"';
    }

    /**
     * Single-pass substitution, so a value containing another placeholder is never expanded again.
     *
     * @param  array<string, string>  $params
     */
    private function fill(string $key, array $params): string
    {
        $pairs = [];

        foreach ($params as $name => $value) {
            $pairs[':'.$name] = $value;
        }

        $template = __($key);

        return strtr(is_string($template) ? $template : $key, $pairs);
    }

    /** @param  array<array-key, mixed>  $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));
    }

    /**
     * jsonb does not preserve key order, so the hash is taken over the key-sorted form.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function canonical(array $value): array
    {
        ksort($value);

        return array_map(fn (mixed $item): mixed => is_array($item) ? $this->canonical($item) : $item, $value);
    }

    private function reason(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            $first = collect($exception->errors())->flatten()->first();

            return is_string($first) ? $first : $exception->getMessage();
        }

        return $exception->getMessage();
    }
}
