<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Enums\TaskPriorityEnum;
use App\Enums\TaskReminderSlotEnum;
use App\Enums\TaskSourceEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class TaskUpsertData extends Data
{
    public function __construct(
        #[Required, Max(200)]
        public readonly string $title,
        #[Required]
        public readonly TaskPriorityEnum $priority,
        #[Nullable, Uuid]
        public readonly string|Optional|null $tenant_id = new Optional,
        #[Nullable, Max(5000)]
        public readonly ?string $description = null,
        #[Nullable, Date]
        public readonly ?string $due_at = null,
        #[Nullable, Date]
        public readonly ?string $remind_at = null,
        #[Nullable, DateFormat('Y-m-d')]
        public readonly ?string $remind_on = null,
        #[Nullable]
        public readonly ?TaskReminderSlotEnum $remind_slot = null,
        #[Nullable, Uuid]
        public readonly ?string $assigned_membership_id = null,
        #[Nullable, Max(32)]
        public readonly ?string $taskable_type = null,
        #[Nullable, Uuid]
        public readonly ?string $taskable_id = null,
        public readonly TaskSourceEnum $source = TaskSourceEnum::Manual,
        #[Nullable]
        public readonly ?TaskStatusEnum $status = null,
    ) {}

    public function dueAtUtc(): ?CarbonImmutable
    {
        return $this->due_at === null ? null : CarbonImmutable::parse($this->due_at)->utc();
    }

    public function remindAtUtc(): ?CarbonImmutable
    {
        if ($this->remind_slot !== null && $this->remind_on !== null) {
            return $this->remind_slot->instantOn($this->remind_on);
        }

        $requested = $this->requestedRemindAtUtc();

        return $requested === null ? null : TaskReminderSlotEnum::nextAtOrAfter($requested);
    }

    public function requestedRemindAtUtc(): ?CarbonImmutable
    {
        return $this->remind_at === null ? null : CarbonImmutable::parse($this->remind_at)->utc();
    }

    /** Omitted `tenant_id` means the bound (active) tenant, explicit null means a global task. */
    public function resolvedTenantId(): ?string
    {
        if ($this->tenant_id instanceof Optional) {
            /** @var string|null $bound */
            $bound = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

            return $bound;
        }

        return $this->tenant_id;
    }

    /** Spatie skips rules of absent defaulted properties, so the slot pair is checked here. */
    public static function withValidator(Validator $validator): void
    {
        $validator->sometimes('remind_on', 'required', static fn (Fluent $input): bool => filled($input->get('remind_slot')));
        $validator->sometimes('remind_slot', 'required', static fn (Fluent $input): bool => filled($input->get('remind_on')));
        $validator->sometimes('remind_at', 'prohibited', static fn (Fluent $input): bool => filled($input->get('remind_slot')));
    }

    /** @return array<string, mixed> */
    public static function rules(ValidationContext $context): array
    {
        $payload = is_array($context->payload) ? $context->payload : [];
        /** @var string|null $tenantId */
        $tenantId = array_key_exists('tenant_id', $payload)
            ? $payload['tenant_id']
            : (app()->bound('current_tenant_id') ? app('current_tenant_id') : null);
        $isGlobal = $tenantId === null || $tenantId === '';
        $assignee = Rule::exists('tenant_memberships', 'id')->where('tenant_id', $tenantId)->where('is_active', true);

        if (! (request()->route('task') instanceof Task)) {
            $assignee->whereNotNull('user_id');
        }

        return [
            'status' => [Rule::in(TaskStatusEnum::activeValues())],
            'assigned_membership_id' => $isGlobal ? ['prohibited'] : [$assignee],
            'taskable_type' => [Rule::in(array_keys(Task::TASKABLE_TYPES)), 'required_with:taskable_id'],
            'taskable_id' => ['required_with:taskable_type'],
        ];
    }
}
