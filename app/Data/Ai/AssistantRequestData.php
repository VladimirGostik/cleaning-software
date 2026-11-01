<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Enums\AssistantRoleEnum;
use Closure;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class AssistantRequestData extends Data
{
    public function __construct(
        public readonly string $company_id,
        /** @var AssistantMessageData[] */
        #[DataCollectionOf(AssistantMessageData::class)]
        public readonly array $messages,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $maxMessages = config()->integer('ai.assistant.max_messages', 20);
        $maxChars = config()->integer('ai.assistant.max_message_chars', 4000);
        $maxTotal = config()->integer('ai.assistant.max_total_chars', 12000);

        return [
            'company_id' => ['required', 'uuid'],
            'messages' => [
                'required', 'array', 'min:1', 'max:'.$maxMessages,
                function (string $attribute, mixed $value, Closure $fail) use ($maxTotal): void {
                    $messages = is_array($value) ? array_values($value) : [];
                    $last = $messages[count($messages) - 1] ?? null;
                    $total = array_sum(array_map(static fn (mixed $m): int => is_array($m) && is_string($m['content'] ?? null) ? mb_strlen($m['content']) : 0, $messages));

                    if (! is_array($last) || ($last['role'] ?? null) !== AssistantRoleEnum::User->value) {
                        $fail(__('app.assistant_last_must_be_user'));
                    }

                    if ($total > $maxTotal) {
                        $fail(__('app.assistant_too_long'));
                    }
                },
            ],
            'messages.*.role' => ['required', Rule::enum(AssistantRoleEnum::class)],
            'messages.*.content' => ['required', 'string', 'max:'.$maxChars],
        ];
    }
}
