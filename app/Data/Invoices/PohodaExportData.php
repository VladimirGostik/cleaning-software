<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Closure;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class PohodaExportData extends Data
{
    public function __construct(
        #[Required, Date]
        public readonly string $from,
        #[Required, Date]
        public readonly string $to,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(ValidationContext $context): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
                function (string $attribute, mixed $value, Closure $fail) use ($context): void {
                    $from = data_get($context->payload, 'from');

                    if (! is_string($from) || ! is_string($value) || ! self::isIsoDate($from) || ! self::isIsoDate($value)) {
                        return;
                    }

                    if (Carbon::parse($from)->diffInDays(Carbon::parse($value)) > config()->integer('pohoda.max_range_days')) {
                        $fail(__('app.pohoda_export_range_too_wide'));
                    }
                },
            ],
        ];
    }

    private static function isIsoDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
