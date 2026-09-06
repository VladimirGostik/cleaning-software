<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Enums\JobStatusEnum;
use Closure;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class JobCalendarFilterData extends Data
{
    private const int MAX_RANGE_DAYS = 62;

    public function __construct(
        #[Required, Date]
        public readonly string $from,
        #[Required, Date]
        public readonly string $to,
        #[Nullable, Uuid]
        public readonly ?string $cleaning_object_id = null,
        #[Nullable, Uuid]
        public readonly ?string $assigned_membership_id = null,
        public readonly ?JobStatusEnum $status = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'to' => [
                'required',
                'date',
                'after_or_equal:from',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $from = request()->input('from');

                    if (! is_string($from) || ! is_string($value)) {
                        return;
                    }

                    if (Carbon::parse($from)->diffInDays(Carbon::parse($value)) > self::MAX_RANGE_DAYS) {
                        $fail(__('app.job_calendar_range_too_wide'));
                    }
                },
            ],
        ];
    }
}
