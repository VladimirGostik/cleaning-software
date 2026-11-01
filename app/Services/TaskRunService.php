<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Ai\RunsTasks;
use App\Data\Tasks\TaskRunStartData;
use App\Enums\ActionChannelEnum;
use App\Enums\TaskRunStatusEnum;
use App\Exceptions\AiUnavailableException;
use App\Jobs\RunTaskWithAi;
use App\Models\Task;
use App\Models\TaskRun;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Services\Ai\Guard\PromptGuard;
use App\Services\Ai\TaskRunContextBuilder;
use App\Services\Ai\TaskRunFinisher;
use App\Services\Ai\TaskRunRequest;
use App\Services\Ai\TaskRunResult;
use App\Services\Ai\ToolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class TaskRunService
{
    public function __construct(
        private DatabaseManager $db,
        private TaskService $tasks,
        private RunsTasks $runner,
        private ToolContext $context,
        private TaskRunContextBuilder $contextBuilder,
        private TaskRunFinisher $finisher,
        private PromptGuard $guard,
        private TaskRunLookup $lookup,
    ) {}

    public function start(Task $task, TaskRunStartData $data, User $actor): TaskRun
    {
        if ($task->isGlobal() && $data->company_id === null) {
            throw ValidationException::withMessages(['company_id' => [__('app.task_run_company_required')]]);
        }

        Gate::forUser($actor)->authorize('startAiRun', [$task, $data->company_id]);

        if (! $task->isOpen()) {
            throw ValidationException::withMessages(['status' => [__('app.task_not_open_for_run')]]);
        }

        $this->expireIfStale($this->lookup->latest($task));

        if ($this->lookup->livePendingActions($task)->where('user_id', $actor->id)->exists()) {
            throw ValidationException::withMessages(['status' => [__('app.task_run_has_pending_actions')]]);
        }

        if (! $this->runner->isAvailable()) {
            throw ValidationException::withMessages(['ai' => [__('app.task_run_ai_unavailable')]]);
        }

        $task->unsetRelation('latestAiRun');

        try {
            return $this->db->transaction(function () use ($task, $data, $actor): TaskRun {
                $run = TaskRun::create([
                    'tenant_id' => $task->isGlobal() ? $data->company_id : $task->tenant_id,
                    'task_id' => $task->id,
                    'started_by_user_id' => $actor->id,
                    'status' => TaskRunStatusEnum::Queued,
                ]);

                RunTaskWithAi::dispatch($run->id)->onQueue(config()->string('ai.task_run.queue', 'ai'))->afterCommit();

                return $run;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['status' => [__('app.task_run_already_active')]]);
        }
    }

    /** Job entry. Claims the run atomically, so a second delivery or a sweep that got there first does nothing. */
    public function execute(string $runId): void
    {
        $run = $this->claim($runId);

        if ($run === null) {
            return;
        }

        $starter = User::query()->find($run->started_by_user_id);

        try {
            $task = $this->tasks->findForSystem($run->task_id);
        } catch (ModelNotFoundException) {
            $this->finisher->endWithNotice($run->id, TaskRunStatusEnum::Failed, 'task_run_notice_task_gone');

            return;
        }

        if ($starter === null || ! $starter->is_active) {
            $this->finisher->endWithNotice($run->id, TaskRunStatusEnum::Failed, 'task_run_notice_not_allowed');

            return;
        }

        try {
            $this->context->run($starter, $run->tenant_id, fn () => $this->attempt($run, $task, $starter));
        } catch (AuthorizationException) {
            $this->finisher->endWithNotice($run->id, TaskRunStatusEnum::Failed, 'task_run_notice_not_allowed');
        }
    }

    public function markFailed(string $runId): void
    {
        $this->finisher->endWithNotice($runId, TaskRunStatusEnum::Failed, 'task_run_notice_lost');
    }

    /** Scale-to-zero hosting has no worker watching: whoever reads or starts a run ends one that can no longer be alive. */
    public function expireIfStale(?TaskRun $run): ?TaskRun
    {
        if ($run === null || ! $run->isStale()) {
            return $run;
        }

        $this->endIfStale($run->id);

        return $run->refresh();
    }

    public function failStale(): int
    {
        /** @var Collection<int, string> $ids */
        $ids = TaskRun::query()
            ->withoutGlobalScope(TenantScope::class)
            ->stale()
            ->orderBy('created_at')
            ->limit(200)
            ->pluck('id');

        foreach ($ids as $id) {
            $this->endIfStale($id);
        }

        return $ids->count();
    }

    /** Staleness is re-checked on the locked row: a worker may have claimed the run since it was read. */
    private function endIfStale(string $runId): void
    {
        $this->finisher->endWithNotice($runId, TaskRunStatusEnum::Failed, 'task_run_notice_lost', onlyIfStale: true);
    }

    private function attempt(TaskRun $run, Task $task, User $starter): void
    {
        $companyId = $task->isGlobal() ? $run->tenant_id : null;

        if (! Gate::forUser($starter)->allows('startAiRun', [$task, $companyId])) {
            $this->finisher->endWithNotice($run->id, TaskRunStatusEnum::Failed, 'task_run_notice_not_allowed');

            return;
        }

        $request = $this->contextBuilder->build($task, $run, $starter);
        $flaggedSource = $this->flaggedSource($request, $task, $starter);

        if ($flaggedSource !== null) {
            $this->finisher->endWithNotice($run->id, TaskRunStatusEnum::Blocked, 'task_run_notice_blocked_'.$flaggedSource);

            return;
        }

        try {
            $result = $this->runner->run($request, $starter);
        } catch (AiUnavailableException) {
            $result = TaskRunResult::providerFailed();
        }

        $this->finisher->finish($run, $task, $starter, $result);
    }

    /** What the model is about to read is scanned value by value; a personal task's hit is recorded without a company. */
    private function flaggedSource(TaskRunRequest $request, Task $task, User $starter): ?string
    {
        $sources = [
            'task' => [$request->title, $request->description, $request->subjectLabel],
            'comment' => [...array_column($request->comments, 'body'), ...array_column($request->comments, 'author')],
        ];

        foreach ($sources as $source => $values) {
            $verdict = $this->guard->scanValues($values);

            if ($verdict->flagged) {
                $this->guard->record($starter, 'task_run:'.$source, $task->tenant_id, ActionChannelEnum::TaskRun, $verdict);

                return $source;
            }
        }

        return null;
    }

    private function claim(string $runId): ?TaskRun
    {
        $claimed = TaskRun::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereKey($runId)
            ->where('status', TaskRunStatusEnum::Queued->value)
            ->update(['status' => TaskRunStatusEnum::Running->value, 'started_at' => now()]);

        return $claimed === 1 ? TaskRun::query()->withoutGlobalScope(TenantScope::class)->find($runId) : null;
    }
}
