<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Enums\TaskStatusEnum;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskStatusChangeData extends Data
{
    public function __construct(
        #[Required]
        public readonly TaskStatusEnum $status,
    ) {}
}
