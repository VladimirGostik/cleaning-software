<?php

declare(strict_types=1);

namespace App\Data\Objects;

use Closure;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class ObjectVisitItemsSyncData extends Data
{
    public const int MAX_ITEMS = 50;

    /** @param DataCollection<int, ObjectVisitItemInputData> $items */
    public function __construct(
        #[DataCollectionOf(ObjectVisitItemInputData::class), LiteralTypeScriptType('App.Data.Objects.ObjectVisitItemInputData[]')]
        public readonly DataCollection $items,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'items' => [
                'bail',
                'present',
                'array',
                'max:'.self::MAX_ITEMS,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }

                    $names = [];

                    foreach ($value as $row) {
                        $name = is_array($row) && is_string($row['name'] ?? null) ? mb_strtolower(trim($row['name'])) : null;

                        if ($name === null || $name === '') {
                            continue;
                        }

                        if (in_array($name, $names, true)) {
                            $fail(__('app.object_visit_items_names_distinct'));

                            return;
                        }

                        $names[] = $name;
                    }
                },
            ],
            'items.*.price' => ['bail', 'required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        $invalid = __('app.object_visit_items_invalid');

        return [
            'items.present' => $invalid,
            'items.array' => $invalid,
            'items.max' => $invalid,
        ];
    }
}
