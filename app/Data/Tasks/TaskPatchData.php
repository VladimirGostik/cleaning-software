<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Enums\TaskPriorityEnum;
use App\Enums\TaskReminderSlotEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Validator as ConcreteValidator;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class TaskPatchData extends Data
{
    public function __construct(
        #[Max(200)]
        public readonly string|Optional $title = new Optional,
        public readonly TaskPriorityEnum|Optional $priority = new Optional,
        #[Nullable, Max(5000)]
        public readonly string|Optional|null $description = new Optional,
        #[Nullable, Date]
        public readonly string|Optional|null $due_at = new Optional,
        #[Nullable, DateFormat('Y-m-d')]
        public readonly string|Optional|null $remind_on = new Optional,
        #[Nullable]
        public readonly TaskReminderSlotEnum|Optional|null $remind_slot = new Optional,
        #[Nullable, Date]
        public readonly string|Optional|null $remind_at = new Optional,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'title' => ['sometimes', 'required'],
            'priority' => ['sometimes', 'required'],
        ];
    }

    /** A `sometimes` rule never runs on an absent key, so the pair is checked on key presence. */
    public static function withValidator(Validator $validator): void
    {
        $validator->sometimes('remind_on', 'required', static fn (Fluent $input): bool => filled($input->get('remind_slot')));
        $validator->sometimes('remind_slot', 'required', static fn (Fluent $input): bool => filled($input->get('remind_on')));
        $validator->sometimes('remind_at', 'prohibited', static fn (Fluent $input): bool => array_key_exists('remind_on', $input->toArray()) || array_key_exists('remind_slot', $input->toArray()));

        $validator->after(static function (Validator $validator): void {
            if (! $validator instanceof ConcreteValidator) {
                return;
            }

            $payload = (array) $validator->getData();
            $hasOn = array_key_exists('remind_on', $payload);

            if ($hasOn !== array_key_exists('remind_slot', $payload)) {
                $validator->addFailure($hasOn ? 'remind_slot' : 'remind_on', 'required');
            }
        });
    }
}
