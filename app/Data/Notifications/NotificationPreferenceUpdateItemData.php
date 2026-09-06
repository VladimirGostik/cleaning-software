<?php

declare(strict_types=1);

namespace App\Data\Notifications;

use App\Enums\NotificationTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NotificationPreferenceUpdateItemData extends Data
{
    public function __construct(
        #[Required]
        public readonly NotificationTypeEnum $type,
        public readonly bool $mail,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'type' => [Rule::in(NotificationTypeEnum::configurableValues())],
        ];
    }
}
