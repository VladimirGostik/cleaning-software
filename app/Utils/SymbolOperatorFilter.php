<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

final class SymbolOperatorFilter implements Filter
{
    public function __construct(
        private readonly string $column,
        private readonly string $likeOperator = 'like',
    ) {}

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if (is_array($value)) {
            $query->where(function (Builder $query) use ($value, $property): void {
                foreach ($value as $item) {
                    $this->__invoke($query, $item, $property);
                }
            });

            return;
        }

        [$operator, $normalizedValue] = SymbolOperators::parse($value);

        if ($normalizedValue === null || $normalizedValue === '') {
            return;
        }

        match ($operator) {
            '~' => $query->where(
                $this->column,
                $this->likeOperator,
                '%'.Filters::escapeLikeValue((string) $normalizedValue).'%',
            ),

            'between' => $this->whereBetween($query, $normalizedValue),

            default => $query->where($this->column, $operator, $normalizedValue),
        };
    }

    private function whereBetween(Builder $query, mixed $value): void
    {
        if (! is_string($value)) {
            return;
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $part): bool => $part !== '',
        ));

        if (count($parts) !== 2) {
            return;
        }

        $query->whereBetween($this->column, [$parts[0], $parts[1]]);
    }
}
