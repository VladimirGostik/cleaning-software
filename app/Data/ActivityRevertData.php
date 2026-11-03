<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ActivityRevertBlockReasonEnum;
use App\Enums\ActivityRevertStatusEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ActivityRevertData extends Data
{
    public function __construct(
        public readonly ActivityRevertStatusEnum $status,
        public readonly ?ActivityRevertBlockReasonEnum $reason,
        public readonly int $changes_count,
        public readonly int $batch_size,
        public readonly ?int $blocking_activity_id,
        public readonly ?string $reverted_at,
        public readonly ?string $reverted_by_name,
        public readonly ?int $reverted_by_activity_id,
        public readonly ?int $reverts_activity_id,
    ) {}
}
