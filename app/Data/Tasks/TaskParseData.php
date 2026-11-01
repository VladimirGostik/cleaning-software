<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class TaskParseData extends Data
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $company_id = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:'.config()->integer('ai.task_parse_max_chars', 2000)],
            'company_id' => ['nullable', 'uuid'],
        ];
    }
}
