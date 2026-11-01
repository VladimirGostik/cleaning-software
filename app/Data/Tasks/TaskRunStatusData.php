<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Enums\TaskRunStatusEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskRunStatusData extends Data
{
    public function __construct(
        public readonly ?string $latest_run_id,
        public readonly ?TaskRunStatusEnum $status,
        public readonly bool $is_active,
    ) {}
}
