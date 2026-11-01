<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\Ai\ExtractsTaskDraft;
use App\Data\Tasks\TaskDraftData;
use App\Data\Tasks\TaskParseData;
use App\Enums\PermissionEnum;
use App\Exceptions\AiUnavailableException;
use App\Models\Task;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Ai\Guard\PromptGuard;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class TaskParseService
{
    public function __construct(
        private ExtractsTaskDraft $extractor,
        private TenantPermissionSnapshot $snapshot,
        private PromptGuard $promptGuard,
    ) {}

    public function parse(TaskParseData $data, User $actor): TaskDraftData
    {
        Gate::forUser($actor)->authorize('create', [Task::class, $data->company_id]);

        $creatable = array_values(array_filter(
            $this->snapshot->forRequest($actor),
            static fn (TenantVisibility $visibility): bool => in_array(PermissionEnum::CreateTasks, $visibility->permissions, true),
        ));
        $timezone = config()->string('ai.timezone', 'Europe/Bratislava');
        $members = $this->members($creatable, $data->company_id);

        $context = new TaskExtractionContext(
            today: CarbonImmutable::now($timezone)->toDateString(),
            timezone: $timezone,
            companies: array_map(static fn (TenantVisibility $visibility): array => ['id' => $visibility->tenant->id, 'name' => $visibility->tenant->name], $creatable),
            companyId: $data->company_id,
            members: array_map(static fn (TenantMembership $membership): array => ['id' => $membership->id, 'name' => $membership->display_name], $members),
        );

        try {
            $draft = $this->extractor->extract($data->text, $context);
        } catch (AiUnavailableException) {
            throw ValidationException::withMessages(['ai' => [__('app.ai_unavailable')]]);
        }

        return $this->guard($draft, $context, $timezone);
    }

    /**
     * @param  list<TenantVisibility>  $creatable
     * @return list<TenantMembership>
     */
    private function members(array $creatable, ?string $companyId): array
    {
        $visibility = $companyId === null ? null : array_find($creatable, static fn (TenantVisibility $v): bool => $v->tenant->id === $companyId);

        if ($visibility === null || ! in_array(PermissionEnum::EditTasks, $visibility->permissions, true)) {
            return [];
        }

        return array_values(TenantMembership::query()
            ->where('tenant_id', $visibility->tenant->id)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->with('user:id,name,email')
            ->get()
            ->all());
    }

    private function guard(TaskDraftData $draft, TaskExtractionContext $context, string $timezone): TaskDraftData
    {
        $eligible = array_column($context->companies, 'id');
        $tenantId = $context->companyId ?? (in_array($draft->tenant_id, $eligible, true) ? $draft->tenant_id : null);
        $memberIds = array_column($context->members, 'id');
        $assignee = $tenantId === $context->companyId && in_array($draft->assigned_membership_id, $memberIds, true) ? $draft->assigned_membership_id : null;

        $verdict = $this->promptGuard->scanValues([$draft->title, $draft->description, $draft->assignee_hint]);

        if ($verdict->flagged) {
            $this->promptGuard->record(null, 'task_parse', $tenantId, null, $verdict);
            $assignee = null;
        }

        return new TaskDraftData(
            title: Str::limit($this->clean(PromptGuard::stripInvisible($draft->title)), 200, ''),
            description: $draft->description === null || $verdict->flagged ? null : Str::limit($this->clean(PromptGuard::stripInvisible($draft->description), true), 5000, ''),
            due_at: $this->utc($draft->due_at, $timezone),
            priority: $draft->priority,
            tenant_id: $tenantId,
            assigned_membership_id: $assignee,
            assignee_hint: $draft->assignee_hint === null ? null : Str::limit($this->clean($draft->assignee_hint), 100, ''),
        );
    }

    private function utc(?string $value, string $timezone): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone)->utc()->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private function clean(string $value, bool $keepNewlines = false): string
    {
        $stripped = (string) preg_replace($keepNewlines ? '/[^\P{C}\n]+/u' : '/[\p{C}]+/u', ' ', strip_tags($value));

        return trim($stripped);
    }
}
