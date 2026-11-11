<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Enums\AssistantRoleEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AssistantMessageData extends Data
{
    /** @param  list<string>  $attachment_names */
    public function __construct(
        public readonly AssistantRoleEnum $role,
        public readonly string $content = '',
        public readonly array $attachment_names = [],
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['content'] ??= '';
        $properties['attachment_names'] ??= [];

        return $properties;
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(AssistantRoleEnum::class)],
            'content' => ['nullable', 'string'],
            'attachment_names' => ['sometimes', 'array'],
        ];
    }
}
