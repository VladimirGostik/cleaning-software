<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Enums\TaskActivityFieldEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskActivityChangeData extends Data
{
    public function __construct(
        public readonly TaskActivityFieldEnum $field,
        public readonly ?string $from,
        public readonly ?string $to,
    ) {}
}
