<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Enums\TaskPriorityEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskDraftData extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $due_at,
        public readonly TaskPriorityEnum $priority,
        public readonly ?string $tenant_id,
        public readonly ?string $assigned_membership_id,
        public readonly ?string $assignee_hint,
    ) {}
}
