<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use Closure;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class MyJobsFilterData extends Data
{
    public function __construct(
        #[Required, DateFormat('Y-m-d')]
        public readonly string $from,
        #[Required, DateFormat('Y-m-d')]
        public readonly string $to,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $max = config()->integer('scheduling.my_jobs.max_range_days');

        return [
            'from' => ['bail', 'required', 'date_format:Y-m-d'],
            'to' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
                function (string $attribute, mixed $value, Closure $fail) use ($max): void {
                    $from = request()->input('from');

                    if (! is_string($from) || ! is_string($value) || ! self::isDay($from) || ! self::isDay($value)) {
                        return;
                    }

                    if (Carbon::parse($from)->diffInDays(Carbon::parse($value)) > $max) {
                        $fail(__('app.my_jobs_range_too_wide', ['days' => $max]));
                    }
                },
            ],
        ];
    }

    private static function isDay(string $value): bool
    {
        return Carbon::hasFormat($value, 'Y-m-d');
    }
}
