<?php

declare(strict_types=1);

namespace App\Data\Ai;

use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class AssistantConversationRenameData extends Data
{
    public function __construct(
        public readonly string $title,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        if (is_string($properties['title'] ?? null)) {
            $properties['title'] = trim($properties['title']);
        }

        return $properties;
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return ['title' => ['required', 'string', 'max:'.config()->integer('ai.assistant.history.max_title_chars')]];
    }
}
